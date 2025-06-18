<?php

declare(strict_types=1);

namespace Multitron\Execution;

interface ExecutionFactory
{
    /**
     * Launch a task in a worker process.
     *
     * @param array<string, mixed> $options
     * @param int $remainingTasks Number of tasks still to start including this one
     */
    public function launch(string $commandName, string $taskId, array $options, int $remainingTasks): Execution;

    public function shutdown(): void;
}
