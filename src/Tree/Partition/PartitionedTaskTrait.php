<?php

declare(strict_types=1);

namespace Multitron\Tree\Partition;

trait PartitionedTaskTrait
{
    protected int $partitionIndex = 0;

    protected int $partitionCount = 1;

    public function setPartitioning(int $index, int $count): void
    {
        $this->partitionIndex = $index;
        $this->partitionCount = $count;
    }
}
