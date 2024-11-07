<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use InvalidArgumentException;

class ArrayTaskNodeGroup extends TaskNode
{
    /**
     * @param TaskNode[] $nodes
     */
    public function __construct(string $id, private readonly array $nodes)
    {
        parent::__construct($id);
        $this->validateNodes($nodes);
    }

    /**
     * @return TaskNode[]
     */
    protected function getNodes(): iterable
    {
        return $this->nodes;
    }

    /**
     * Validates that all elements in the array are instances of TaskNode.
     *
     * @param TaskNode[] $nodes
     * @throws InvalidArgumentException if the array contains non-TaskNode elements.
     */
    private function validateNodes(array $nodes): void
    {
        foreach ($nodes as $node) {
            if (!$node instanceof TaskNode) {
                throw new InvalidArgumentException('All elements of $nodes must be instances of TaskNode.');
            }
        }
    }
}
