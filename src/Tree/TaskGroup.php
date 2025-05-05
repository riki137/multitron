<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;

final class TaskGroup implements TaskNode
{
    public function __construct(private readonly string $id, private readonly array $children = [], private readonly array $dependencies = [])
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFactory(): ?callable
    {
        return null;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getChildren(): array
    {
        return $this->children;
    }
}
