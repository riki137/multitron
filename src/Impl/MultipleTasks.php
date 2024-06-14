<?php

declare(strict_types=1);

namespace Multitron\Impl;

use Multitron\Container\Def\TaskDefinition;

class MultipleTasks implements TaskGroup
{
    /**
     * @var TaskDefinition[]
     */
    private readonly array $tasks;

    public function __construct(TaskDefinition ...$tasks)
    {
        $this->tasks = $tasks;
    }

    public function getTasks(): iterable
    {
        return $this->tasks;
    }

    public function getTask(string $parentId, string $index): TaskDefinition
    {
        return $this->tasks[$index];
    }

}
