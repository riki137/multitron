<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Closure;
use Multitron\Comms\TaskCommunicator;
use Multitron\Impl\ClosureTask;
use Multitron\Impl\Task;

/**
 * Represents a task node that uses a closure as its task.
 */
class ClosureTaskNode extends TaskNodeLeaf
{
    /**
     * @var Closure(TaskCommunicator): void The closure representing the task.
     */
    private readonly Closure $task;

    /**
     * @param string $id The unique identifier for this node.
     * @param Closure(TaskCommunicator): void $task The closure to execute as a task.
     */
    public function __construct(string $id, Closure $task)
    {
        parent::__construct($id);
        $this->task = $task;
    }

    public function getTask(): Task
    {
        return new ClosureTask($this->task);
    }
}
