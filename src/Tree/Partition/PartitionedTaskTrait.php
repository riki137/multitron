<?php

declare(strict_types=1);

namespace Multitron\Tree\Partition;

trait PartitionedTaskTrait
{
    protected int $partitionIndex = 0;

    protected int $partitionCount = 1;

    /**
     * Provide the index of this partition and the total number of partitions
     * that exist. Implementations usually call this before execution begins.
     */
    public function setPartitioning(int $index, int $count): void
    {
        $this->partitionIndex = $index;
        $this->partitionCount = $count;
    }
}
