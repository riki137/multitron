<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Closure;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Message\ContainerLoadedMessage;
use Multitron\Message\StartTaskMessage;
use Multitron\Orchestrator\TaskState;
use RuntimeException;
use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Transport\TimeoutException;

final class ProcessExecutionFactory implements ExecutionFactory
{
    public const DEFAULT_TIMEOUT = 8.0;

    /** @var array<array{ProcessExecution, ResponsePromise}> */
    private array $processes = [];

    private bool $initialized = false;

    /** @var list<string> */
    private array $errors = [];

    private Closure $errorCatcher;

    private readonly NativeIpcPeer $ipcPeer;

    private readonly int $processBufferSize;

    private readonly float $timeout;

    /**
     * @param int|null $processBufferSize number of workers to keep buffered
     * @param float $timeout seconds before IPC requests fail
     */
    public function __construct(NativeIpcPeer $ipcPeer, ?int $processBufferSize = null, float $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->ipcPeer = $ipcPeer;
        $this->processBufferSize = (int)max(1, $processBufferSize ?? (CpuDetector::getCpuCount() / 1.6));
        $this->timeout = $timeout;
        $this->errorCatcher = $this->errorCatcherFn(...);
    }

    private function buffer(): void
    {
        $process = new ProcessExecution($this->ipcPeer);
        $process->getSession()->onMessage($this->errorCatcher);
        $response = $process->getSession()->request(new ContainerLoadedMessage(), $this->timeout);
        $this->processes[] = [$process, $response];
    }

    private function errorCatcherFn(Message $message): void
    {
        if ($message instanceof LogMessage) {
            $this->errors[] = '[' . $message->level . '] ' . $message->message;
        }
    }

    /**
     * Terminate the specified worker process and gather diagnostic
     * information such as exit code and captured output for error reporting.
     */
    private function killAndComposeMessage(ProcessExecution $process): string
    {
        $result = $process->kill();
        $details = [];
        if ($this->errors !== []) {
            $details[] = implode(PHP_EOL, $this->errors);
        }
        $this->errors = [];

        $details[] = 'Worker exited with code ' . var_export($result['exitCode'], true);
        $details[] = 'STDOUT: ' . trim($result['stdout']);
        $details[] = 'STDERR: ' . trim($result['stderr']);

        return implode(PHP_EOL, $details);
    }

    /**
     * Retrieve a worker process from the internal buffer, waiting for it to
     * finish initialisation if necessary. Additional processes are spawned to
     * keep the buffer filled based on the number of remaining tasks.
     */
    private function obtain(int $remainingTasks): ProcessExecution
    {
        if (!$this->initialized) {
            $this->initialized = true;
        }

        $required = min($this->processBufferSize, max(0, $remainingTasks)) + 1;
        while (count($this->processes) < $required) {
            $this->buffer();
        }
        $entry = array_shift($this->processes);
        if ($entry === null) {
            throw new RuntimeException('No buffered process available (should not happen)');
        }
        /** @var ProcessExecution $process */
        [$process, $response] = $entry;
        try {
            $response->await();
            $process->getSession()->offMessage($this->errorCatcher);
        } catch (TimeoutException $e) {
            $message = $e->getMessage() . PHP_EOL . $this->killAndComposeMessage($process);
            throw new RuntimeException($message);
        }
        return $process;
    }

    /**
     * @param array<string, mixed> $options
     * @param int $remainingTasks Number of tasks still to start not including this one
     */
    public function launch(
        string $commandName,
        string $taskId,
        array $options,
        int $remainingTasks,
        IpcHandlerRegistry $registry,
        ?callable $onException = null
    ): TaskState {
        $execution = $this->obtain($remainingTasks);

        $state = new TaskState($taskId, $execution);
        $registry->attach($state);
        if ($onException !== null) {
            $execution->getSession()->onException($onException);
        }

        // send the task id to the worker over IPC
        try {
            $execution->getSession()->request(new StartTaskMessage($commandName, $taskId, $options))->await();
        } catch (TimeoutException $e) {
            $message = 'Task startup timed out: ' . $e->getMessage() . PHP_EOL .
                $this->killAndComposeMessage($execution);
            throw new RuntimeException($message);
        }

        return $state;
    }

    public function shutdown(): void
    {
        foreach ($this->processes as [$process]) {
            $process->kill();
        }
        $this->processes = [];
    }

    public function __destruct()
    {
        $this->shutdown();
    }
}
