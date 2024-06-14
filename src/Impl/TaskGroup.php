<?php
declare(strict_types=1);

namespace Multitron\Impl;

use Multitron\Container\Def\TaskDefinition;

interface TaskGroup
{
    /**
     * @return iterable<string, TaskDefinition>
     */
    public function getTasks(): iterable;

    public function getTask(string $index): TaskDefinition;
}
