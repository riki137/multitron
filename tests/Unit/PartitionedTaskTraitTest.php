<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Tree\Partition\PartitionedTaskTrait;
use PHPUnit\Framework\TestCase;

final class PartitionedTaskTraitTest extends TestCase
{
    public function testSetPartitioningUpdatesProperties(): void
    {
        $task = new class {
            use PartitionedTaskTrait;

            public function getIndex(): int
            {
                return $this->partitionIndex;
            }

            public function getCount(): int
            {
                return $this->partitionCount;
            }
        };

        $task->setPartitioning(2, 5);
        $this->assertSame(2, $task->getIndex());
        $this->assertSame(5, $task->getCount());
    }
}
