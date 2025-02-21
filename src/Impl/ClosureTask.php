<?php

declare(strict_types=1);

namespace Multitron\Impl;

use Closure;
use Multitron\Comms\TaskCommunicator;

/**
 * Represents a task that is executed through a closure.
 */
class ClosureTask implements Task
{
    private readonly Closure $task;

    /**
     * @param Closure(TaskCommunicator): void $task The closure representing the task.
     * The closure should accept a TaskCommunicator object as its only parameter and return void.
     */
    public function __construct(Closure $task)
    {
        $this->task = $task;
    }

    /**
     * Executes the task.
     *
     * @param TaskCommunicator $comm An instance of TaskCommunicator used for task execution.
     *
     * @return void
     */
    public function execute(TaskCommunicator $comm): void
    {
        ($this->task)($comm);
        if ($comm->getProgress()->isPristine()) {
            $comm->addDone();
            $comm->setTotal(1);
        }
    }
}
