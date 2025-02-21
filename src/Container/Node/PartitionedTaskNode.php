<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use LogicException;
use Multitron\Impl\PartitionedTask;
use Multitron\Impl\Task;

/**
 * Represents a node that handles partitioned tasks within the task tree.
 * This node type allows splitting a task into multiple parts for parallel processing.
 */
class PartitionedTaskNode extends TaskNodeLeaf
{
    /**
     * @param string $baseId The base identifier for the task
     * @param TaskNodeLeaf $sourceNode The source node containing the original task
     * @param int $index The partition index (zero-based)
     * @param int $modulo The total number of partitions
     * @throws LogicException If the partitioning parameters are invalid
     */
    public function __construct(
        string $baseId,
        private readonly TaskNodeLeaf $sourceNode,
        private readonly int $index,
        private readonly int $modulo
    ) {
        $this->validatePartitioningParams($index, $modulo);
        parent::__construct(sprintf('%s: %d/%d', $baseId, $index + 1, $modulo));
    }

    /**
     * Retrieves the partitioned task with the configured partitioning parameters.
     *
     * @return Task The configured partitioned task
     * @throws LogicException If the source task is not an instance of PartitionedTask
     */
    public function getTask(): Task
    {
        $task = clone $this->sourceNode->getTask();

        if (!$task instanceof PartitionedTask) {
            throw new LogicException(sprintf(
                'Task of class %s must implement %s interface',
                get_class($task),
                PartitionedTask::class
            ));
        }

        $task->setPartitioning($this->index, $this->modulo);
        return $task;
    }

    /**
     * Validates the partitioning parameters.
     *
     * @param int $index The partition index
     * @param int $modulo The total number of partitions
     * @throws LogicException If the parameters are invalid
     */
    private function validatePartitioningParams(int $index, int $modulo): void
    {
        if ($modulo < 1) {
            throw new LogicException('Modulo must be greater than 0');
        }

        if ($index < 0) {
            throw new LogicException('Index must be greater than or equal to 0');
        }

        if ($index >= $modulo) {
            throw new LogicException(sprintf(
                'Index (%d) must be less than modulo (%d)',
                $index,
                $modulo
            ));
        }
    }
}
