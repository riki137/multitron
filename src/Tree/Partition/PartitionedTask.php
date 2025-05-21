<?php

declare(strict_types=1);

namespace Multitron\Tree\Partition;

abstract class PartitionedTask implements PartitionedTaskInterface
{
    use PartitionedTaskTrait;
}
