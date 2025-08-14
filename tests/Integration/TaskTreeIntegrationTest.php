<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
use Multitron\Orchestrator\TaskList;
use Multitron\Tree\CompiledTaskNode;
use Multitron\Tree\Partition\PartitionedTask;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeQueue;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class TaskTreeIntegrationTest extends TestCase
{
    private function createTaskList(): TaskList
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): object
            {
                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }
        };

        $builder = new TaskTreeBuilder($container);

        $service = $builder->service(DummyTask::class);
        $manual = $builder->task('B', fn() => new DummyTask(), [$service->id]);
        $group = $builder->group('group1', [$service, $manual]);
        $partitioned = $builder->partitioned(DummyPartitionTask::class, 2, ['group1']);

        $root = $builder->group('root', [$group, $partitioned]);

        return new TaskList($root);
    }

    public function testCompilerWithGroupsAndPartitionedTasks(): void
    {
        $list = $this->createTaskList();
        $nodes = $list->toArray();

        $this->assertCount(4, $nodes);
        $this->assertArrayHasKey('DummyTask', $nodes);
        $this->assertArrayHasKey('B', $nodes);
        $this->assertArrayHasKey('DummyPartitionTask 1/2', $nodes);
        $this->assertArrayHasKey('DummyPartitionTask 2/2', $nodes);

        $this->assertSame([], $nodes['DummyTask']->dependencies);
        $this->assertSame(['DummyTask'], $nodes['B']->dependencies);
        $this->assertSame(['DummyTask', 'B'], $nodes['DummyPartitionTask 1/2']->dependencies);

        $this->assertSame(['root', 'group1'], $nodes['DummyTask']->tags);
        $this->assertSame(['root', 'DummyPartitionTask'], $nodes['DummyPartitionTask 1/2']->tags);
    }

    public function testQueueOrderingAndSkipOnFailure(): void
    {
        $list = $this->createTaskList();
        $queue = new TaskTreeQueue($list, 2);

        $iterator = $queue->getIterator();

        // 1) first yield must be our DummyTask
        $this->assertTrue($iterator->valid(), 'Expected at least one yield');
        $first = $iterator->current();
        $this->assertInstanceOf(CompiledTaskNode::class, $first);
        $this->assertSame('DummyTask', $first->id);

        // 2) second yield should be null (i.e. “wait”)
        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertNull($iterator->current());

        // mark DummyTask done so B becomes available
        $queue->markCompleted('DummyTask');

        // 3) third yield must be task “B”
        $iterator->next();
        $this->assertTrue($iterator->valid());
        $second = $iterator->current();
        $this->assertInstanceOf(expected: CompiledTaskNode::class, actual: $second);
        $this->assertSame('B', $second->id);

        // fail B and verify its two partitions are skipped
        $skipped = $queue->markFailed('B');
        sort($skipped);
        $this->assertSame([
            'DummyPartitionTask 1/2',
            'DummyPartitionTask 2/2',
        ], $skipped);

        // advance once more — should now be done
        $iterator->next();
        $this->assertFalse($iterator->valid());
    }
}

final class DummyTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
    }
}

final class DummyPartitionTask extends PartitionedTask
{
    public function execute(TaskCommunicator $comm): void
    {
    }
}
