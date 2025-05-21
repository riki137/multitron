<?php

declare(strict_types=1);

namespace Multitron\Tree\Partition;

use Multitron\Execution\Task;

interface PartitionedTaskInterface extends Task
{
    public function setPartitioning(int $index, int $count): void;
}
