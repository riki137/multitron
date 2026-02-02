<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;

interface TaskNodeFactory
{
    /**
     * @param array<TaskNode|string> $dependencies
     * @param string[] $tags
     */
    public function create(string $id, Closure $factory, array $dependencies = [], array $tags = [], ?string $class = null): TaskNode;
}
