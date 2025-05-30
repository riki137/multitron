<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;

final readonly class TaskNode
{
    private function __construct(
        public string $id,
        public ?Closure $factory, // null ⇒ group
        public array $dependencies, // raw deps (leaf or group IDs)
        public array $children // only for groups
    ) {
    }

    public static function leaf(string $id, Closure $factory, array $deps = []): self
    {
        return new self($id, $factory, $deps, []);
    }

    public static function group(string $id, array $children, array $deps = []): self
    {
        return new self($id, null, $deps, $children);
    }

    public function isLeaf(): bool
    {
        return $this->factory !== null;
    }
}
