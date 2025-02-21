<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Multitron\Impl\Task;

/**
 * Represents a leaf node in the task hierarchy that contains an actual task implementation.
 *
 * This abstract class serves as a base for concrete task leaf implementations.
 */
abstract class TaskNodeLeaf extends TaskNode
{
    /**
     * Returns the task implementation associated with this leaf node.
     *
     * @return Task The task implementation
     */
    abstract public function getTask(): Task;
}
