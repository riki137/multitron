<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use ArrayIterator;
use IteratorAggregate;
use Multitron\Tree\CompiledTaskNode;
use Multitron\Tree\TaskNode;
use Multitron\Tree\TaskTreeCompiler;
use Traversable;

final readonly class TaskList implements IteratorAggregate
{
    /** @var array<string, CompiledTaskNode> */
    private array $nodes;

    public function __construct(TaskNode $root)
    {
        $compiler = new TaskTreeCompiler();
        $this->nodes = $compiler->compile($root);
    }

    public function get(string $id): ?CompiledTaskNode
    {
        return $this->nodes[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /**
     * Returns all task nodes as an array.
     *
     * @return array<string, CompiledTaskNode>
     */
    public function toArray(): array
    {
        return $this->nodes;
    }

    /**
     * @return Traversable<string, CompiledTaskNode>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->nodes);
    }
}
