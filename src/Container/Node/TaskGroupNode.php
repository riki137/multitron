<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Closure;
use Generator;
use RuntimeException;

class TaskGroupNode extends TaskNode
{
    /**
     * @param string $id
     * @param Closure(): iterable<TaskNode> $factory
     */
    public function __construct(string $id, private readonly Closure $factory)
    {
        parent::__construct($id);
    }

    /**
     * @return iterable<TaskLeafNode>
     */
    public function getTasks(): iterable
    {
        foreach (($this->factory)() ?? [] as $nodes) {
            foreach ($this->unpackNode($nodes) as $node) {
                $node->belongsTo($this->getId());
                foreach ($this->getDependencies() as $dependency) {
                    $node->dependsOn($dependency);
                }
                yield $node;
            }
        }
    }


    private function unpackNode(TaskNode $node): Generator
    {
        if ($node instanceof TaskGroupNode) {
            foreach ($node->getTasks() as $node) {
                yield from $this->unpackNode($node);
            }
        } elseif ($node instanceof TaskLeafNode) {
            yield $node;
        } else {
            throw new RuntimeException();
        }
    }
}
