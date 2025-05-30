<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Closure;
use Multitron\Message\ContainerLoadedMessage;
use Multitron\Message\StartTaskMessage;
use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\IpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use StreamIpc\Transport\TimeoutException;
use RuntimeException;

final class ProcessExecutionFactory implements ExecutionFactory
{
    public const DEFAULT_PROCESS_BUFFER_SIZE = 2;
    public const DEFAULT_TIMEOUT = 8.0;

    /** @var array<array{ProcessExecution, ResponsePromise}> */
    private array $processes = [];

    private bool $initialized = false;

    private array $errors = [];
    private Closure $errorCatcher;

    public function __construct(
        private readonly IpcPeer $ipcPeer,
        private readonly int $processBufferSize = self::DEFAULT_PROCESS_BUFFER_SIZE,
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
    ) {
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
            $this->errors[] = "[" . $message->level . "] " . $message->message;
        }
    }

    private function obtain(): ProcessExecution
    {
        if (!$this->initialized) {
            $this->initialized = true;

            for ($i = 0; $i < $this->processBufferSize; $i++) {
                $this->buffer();
            }
        }

        $this->buffer();
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
     */
    public function launch(string $commandName, string $taskId, array $options): Execution
    {
        $execution = $this->obtain();
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
