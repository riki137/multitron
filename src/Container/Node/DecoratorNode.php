<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use InvalidArgumentException;

/**
 * DecoratorNode class that wraps multiple TaskNodes to enable decorator pattern implementation.
 */
class DecoratorNode extends TaskNode
{
    /** @var TaskNode[] */
    private array $nodes;

    /**
     * @param TaskNode ...$nodes One or more TaskNode instances to decorate
     * @throws InvalidArgumentException If no nodes are provided
     */
    public function __construct(TaskNode ...$nodes)
    {
        parent::__construct();
        $this->nodes = $nodes;
    }

    /**
     * Returns the decorated nodes.
     *
     * @return TaskNode[]
     */
    final protected function getNodes(): array
    {
        return $this->nodes;
    }
}
