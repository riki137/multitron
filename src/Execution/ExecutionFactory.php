<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\TaskState;
use StreamIpc\InvalidStreamException;

interface ExecutionFactory
{
    /**
     * Launch a task in a worker process.
     *
     * @param array<string, mixed> $options
     * @param int $remainingTasks Number of tasks still to start including this one
     * @param ?callable(InvalidStreamException): (void|false) $onException
     */
    public function launch(
        string $commandName,
        string $taskId,
        array $options,
        int $remainingTasks,
        IpcHandlerRegistry $registry,
        ?callable $onException = null
    ): TaskState;

    public function shutdown(): void;
}
