<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Orchestrator\TaskList;
use Multitron\Tests\Mocks\AppContainer;
use Multitron\Tests\Mocks\DummyTask;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeQueue;
use PHPUnit\Framework\TestCase;

final class TaskTreeQueueTest extends TestCase
{
    public function testCompletedTasksAreSkippedAndUnblockDependents(): void
    {
        $builder = new TaskTreeBuilder(new AppContainer());
        $a = $builder->task('A', fn() => new DummyTask());
        $b = $builder->task('B', fn() => new DummyTask(), [$a]);
        $queue = new TaskTreeQueue(new TaskList($builder->group('root', [$a, $b])), 1, ['A', 'gone']);

        $first = $queue->getIterator()->current();
        $this->assertSame('B', $first?->id);
        $queue->markCompleted('B');
        $queue->getIterator()->next();
        $this->assertFalse($queue->getIterator()->valid());
        $this->assertSame(['A', 'B'], $queue->getCompleted());
    }
}
