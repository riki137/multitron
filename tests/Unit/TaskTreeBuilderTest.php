<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use LogicException;
use Multitron\Tests\Mocks\DummyPartitionTask;
use Multitron\Tests\Mocks\DummyTask;
use Multitron\Tests\Mocks\NotATask;
use Multitron\Tree\TaskNode;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeBuilderFactory;
use PHPUnit\Framework\TestCase;
use Pimple\Psr11\Container;

final class TaskTreeBuilderTest extends TestCase
{
    public function testServiceRequiresContainer(): void
    {
        $builder = new TaskTreeBuilder(null);
        $this->expectException(LogicException::class);
        $builder->service(DummyTask::class);
    }

    public function testFactoryWithoutContainerCreatesBuilder(): void
    {
        $factory = new TaskTreeBuilderFactory(null);
        $builder = $factory->create();
        $this->expectException(LogicException::class);
        $builder->service(DummyTask::class);
    }

    public function testContainerUsed(): void
    {
        $container = new Container(new \Pimple\Container([
            DummyTask::class => fn() => new DummyTask(),
        ]));
        $factory = new TaskTreeBuilderFactory($container);
        $builder = $factory->create();

        // This should not throw an exception
        $node = $builder->service(DummyTask::class);
        $this->assertInstanceOf(TaskNode::class, $node);
        $task = ($node->factory)();
        $this->assertInstanceOf(DummyTask::class, $task);
    }

    public function testServiceMustImplementTask(): void
    {
        $container = new Container(new \Pimple\Container([
            NotATask::class => fn() => new NotATask(),
        ]));
        $builder = new TaskTreeBuilder($container);
        $node = $builder->service(NotATask::class);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Service "' . NotATask::class . '" must implement Task interface');
        ($node->factory)();
    }

    public function testPartitionedFactoryThrowsWithoutContainer(): void
    {
        $builder = new TaskTreeBuilder(null);
        $node = $builder->partitioned(DummyPartitionTask::class, 1);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('TaskTreeBuilderFactory has no container injected');
        ($node->children[0]->factory)();
    }

    public function testPartitionedClosureWithNonPartitionedTaskThrows(): void
    {
        $builder = new TaskTreeBuilder(null);
        $node = $builder->partitionedClosure(
            'test',
            fn() => new DummyTask(), // Returns Task, not PartitionedTaskInterface
            2
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected PartitionedTaskInterface, got');
        ($node->children[0]->factory)();
    }

    public function testPartitionedClosureSuccess(): void
    {
        $builder = new TaskTreeBuilder(null);
        $node = $builder->partitionedClosure(
            'test',
            fn() => new DummyPartitionTask(),
            3,
            ['dep1']
        );

        $this->assertCount(3, $node->children);
        $this->assertSame('test', $node->id);
        $this->assertSame(['dep1'], $node->dependencies);
        
        // Test first partition
        $task = ($node->children[0]->factory)();
        $this->assertInstanceOf(DummyPartitionTask::class, $task);
        $this->assertSame('test 1/3', $node->children[0]->id);
        
        // Test second partition
        $task2 = ($node->children[1]->factory)();
        $this->assertInstanceOf(DummyPartitionTask::class, $task2);
        $this->assertSame('test 2/3', $node->children[1]->id);
    }

    public function testPartitionedWithContainer(): void
    {
        $container = new Container(new \Pimple\Container([
            DummyPartitionTask::class => fn() => new DummyPartitionTask(),
        ]));
        $builder = new TaskTreeBuilder($container);
        $node = $builder->partitioned(DummyPartitionTask::class, 2, ['dep'], 'custom-id');

        $this->assertSame('custom-id', $node->id);
        $this->assertCount(2, $node->children);
        $this->assertSame(['dep'], $node->dependencies);
        
        $task = ($node->children[0]->factory)();
        $this->assertInstanceOf(DummyPartitionTask::class, $task);
    }

    public function testServiceWithCustomId(): void
    {
        $container = new Container(new \Pimple\Container([
            DummyTask::class => fn() => new DummyTask(),
        ]));
        $builder = new TaskTreeBuilder($container);
        $node = $builder->service(DummyTask::class, ['dep1', 'dep2'], 'my-custom-id');

        $this->assertSame('my-custom-id', $node->id);
        $this->assertSame(['dep1', 'dep2'], $node->dependencies);
    }

    public function testTaskMethod(): void
    {
        $builder = new TaskTreeBuilder(null);
        $node = $builder->task('test-task', fn() => new DummyTask(), ['dep1']);

        $this->assertSame('test-task', $node->id);
        $this->assertSame(['dep1'], $node->dependencies);
        $this->assertSame([], $node->tags);
        
        $task = ($node->factory)();
        $this->assertInstanceOf(DummyTask::class, $task);
    }

    public function testGroupMethod(): void
    {
        $builder = new TaskTreeBuilder(null);
        $child1 = $builder->task('child1', fn() => new DummyTask());
        $child2 = $builder->task('child2', fn() => new DummyTask());
        
        $group = $builder->group('my-group', [$child1, $child2], ['dep1']);

        $this->assertSame('my-group', $group->id);
        $this->assertSame(['dep1'], $group->dependencies);
        $this->assertCount(2, $group->children);
        $this->assertNull($group->factory);
    }

    public function testPatternFilter(): void
    {
        $builder = new TaskTreeBuilder(null);
        $child1 = $builder->task('child1', fn() => new DummyTask());
        
        $node = $builder->patternFilter('pattern-id', 'some-pattern', [$child1]);

        $this->assertSame('pattern-id', $node->id);
        $this->assertCount(1, $node->children);
    }

    public function testGetPartitionedTaskNotImplementingInterface(): void
    {
        $container = new Container(new \Pimple\Container([
            DummyTask::class => fn() => new DummyTask(),
        ]));
        $builder = new TaskTreeBuilder($container);
        $node = $builder->partitioned(DummyTask::class, 1);
        
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must implement PartitionedTaskInterface');
        ($node->children[0]->factory)();
    }
}

