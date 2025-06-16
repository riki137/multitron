<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Closure;
use Multitron\Message\ContainerLoadedMessage;
use Multitron\Message\StartTaskMessage;
use RuntimeException;
use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\IpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use StreamIpc\Transport\TimeoutException;

final class ProcessExecutionFactory implements ExecutionFactory
{
    public const DEFAULT_TIMEOUT = 8.0;

    /** @var array<array{ProcessExecution, ResponsePromise}> */
    private array $processes = [];

    private bool $initialized = false;

    private array $errors = [];

    private Closure $errorCatcher;

    private readonly IpcPeer $ipcPeer;
    private readonly int $processBufferSize;
    private readonly float $timeout;

    public function __construct(IpcPeer $ipcPeer, ?int $processBufferSize = null, float $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->ipcPeer = $ipcPeer;
        $this->processBufferSize = (int)max( 1, $processBufferSize ?? (CpuDetector::getCpuCount() / 1.6));
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
            $process->kill();
            try {
                throw new RuntimeException($e->getMessage() . PHP_EOL . implode(PHP_EOL, $this->errors));
            } finally {
                $this->errors = [];
            }
        }
        return $process;
    }

    /**
     * @param array<string, mixed> $options
     * @param int $remainingTasks Number of tasks still to start not including this one
     */
    public function launch(string $commandName, string $taskId, array $options, int $remainingTasks): Execution
    {
        $execution = $this->obtain($remainingTasks);
        // send the task id to the worker over IPC
        $execution->getSession()->request(new StartTaskMessage($commandName, $taskId, $options))->await();

        return $execution;
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
