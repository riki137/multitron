<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Multitron\Impl\Task;
use InvalidArgumentException;

/**
 * Represents a leaf node in the task hierarchy that contains an actual task implementation.
 *
 * This abstract class serves as a base for concrete task leaf implementations.
 */
abstract class TaskNodeLeaf extends TaskNode
{
    /**
     * @param string $id Unique identifier for the task node
     */
    public function __construct(private readonly string $id)
    {
        parent::__construct($id);
    }

    /**
     * Returns the task implementation associated with this leaf node.
     *
     * @return Task The task implementation
     */
    abstract public function getTask(): Task;
}
