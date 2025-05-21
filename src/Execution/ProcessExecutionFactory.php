<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Multitron\Message\ContainerLoadedMessage;
use Multitron\Message\StartTaskMessage;
use PhpStreamIpc\Envelope\ResponsePromise;
use PhpStreamIpc\IpcPeer;

final class ProcessExecutionFactory implements ExecutionFactory
{
    public const DEFAULT_PROCESS_BUFFER_SIZE = 4;

    /** @var array<array{ProcessExecution, ResponsePromise}> */
    private array $processes = [];

    private bool $initialized = false;

    public function __construct(
        private readonly IpcPeer $ipcPeer,
        private readonly int $processBufferSize = self::DEFAULT_PROCESS_BUFFER_SIZE,
    ) {
    }

    private function buffer(): void
    {
        $process = new ProcessExecution($this->ipcPeer);
        $response = $process->getSession()->request(new ContainerLoadedMessage());
        $this->processes[] = [$process, $response];
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
        [$process, $response] = array_shift($this->processes);
        $response->await();
        return $process;
    }

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
}
