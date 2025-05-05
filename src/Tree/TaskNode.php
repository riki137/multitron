<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;

interface TaskNode
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @return string[]
     */
    public function getDependencies(): array;

    /**
     * @return iterable<TaskNode[]>
     */
    public function getChildren(): iterable;

    /**
     * @return null|callable(): Task
     */
    public function getFactory(): ?callable;
}
