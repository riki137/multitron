<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use LogicException;
use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
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
}

final class DummyTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
    }
}
