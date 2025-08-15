<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use Multitron\Tree\Partition\PartitionedTaskTrait;

class PartitionedTaskTraitStub
{
    use PartitionedTaskTrait;

    public function getIndex(): int
    {
        return $this->partitionIndex;
    }

    public function getCount(): int
    {
        return $this->partitionCount;
    }
}

