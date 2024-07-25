<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Closure;
use Multitron\Impl\Task;

class TaskLeafNode extends TaskNode
{
    private bool $nonBlocking = false;

    /**
     * @param string $id
     * @param Closure(): Task $factory
     */
    public function __construct(string $id, public readonly Closure $factory)
    {
        parent::__construct($id);
    }

    public function getTask(): Task
    {
        return ($this->factory)();
    }

    /**
     * Use this to mark tasks that can be ran on the same thread because they fully utilize only Revolt Event Loop (AMPHP).
     * This will avoid creating a new process and container for this task and can save some time for simple async tasks.
     * @param bool $async
     * @return $this
     */
    public function setNonBlocking(bool $async = true): static
    {
        $this->nonBlocking = $async;
        return $this;
    }

    public function isNonBlocking(): bool
    {
        return $this->nonBlocking;
    }
}
