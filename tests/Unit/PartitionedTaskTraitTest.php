<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Tests\Mocks\PartitionedTaskTraitStub;
use PHPUnit\Framework\TestCase;

final class PartitionedTaskTraitTest extends TestCase
{
    public function testSetPartitioningUpdatesProperties(): void
    {
        $task = new PartitionedTaskTraitStub();
        $task->setPartitioning(2, 5);
        $this->assertSame(2, $task->getIndex());
        $this->assertSame(5, $task->getCount());
    }
}

