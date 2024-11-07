<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use InvalidArgumentException;

/**
 * Represents a partitioned task group node that splits work into multiple chunks.
 * This node creates multiple PartitionedTaskNode instances, each handling a portion of the work.
 */
final class PartitionedTaskNodeGroup extends TaskNode
{
    /**
     * Creates a new partitioned task group node.
     *
     * @param string $id Unique identifier for the task group
     * @param TaskNodeLeaf $source The source task node that will be cloned for each partition
     * @param int $chunks The number of chunks to partition the work into
     *
     * @throws InvalidArgumentException If chunks is less than 1 or exceeds maximum limit
     * @throws InvalidArgumentException If id is empty
     */
    public function __construct(
        private readonly string $id,
        private readonly TaskNodeLeaf $source,
        private readonly int $chunks
    ) {
        if ($chunks < 1) {
            throw new InvalidArgumentException('Chunks must be greater than or equal to 1');
        }

        parent::__construct($id);
    }

    /**
     * Returns an iterator of PartitionedTaskNode instances.
     * Each node represents a chunk of the original work.
     *
     * @return iterable<PartitionedTaskNode>
     */
    protected function getNodes(): iterable
    {
        for ($i = 1; $i <= $this->chunks; $i++) {
            yield new PartitionedTaskNode(
                $this->id,
                $this->source,
                $i - 1,
                $this->chunks
            );
        }
    }

    /**
     * Get the number of chunks this group is partitioned into.
     *
     * @return int
     */
    public function getChunkCount(): int
    {
        return $this->chunks;
    }

    /**
     * Get the source task node.
     *
     * @return TaskNodeLeaf
     */
    public function getSource(): TaskNodeLeaf
    {
        return $this->source;
    }
}
