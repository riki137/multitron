<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskQueue;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeBuilderFactory;
use Multitron\Tree\ClosureTaskNode;
use Multitron\Tree\SimpleTaskGroupNode;
use Multitron\Tree\TaskLeafNode;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;

final class TaskQueueFailIntegrationTest extends TestCase
{
    private function createContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id) { return null; }
            public function has(string $id): bool { return false; }
        };
    }

    private function createDummyTask(): Task
    {
        return new class implements Task {
            public function execute(TaskCommunicator $comm): void {}
        };
    }

    public function testFailingTaskSkipsDependentsOutsideGroup(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);
        $builder   = $factory->create();

        $taskA = new ClosureTaskNode('taskA', fn() => $this->createDummyTask());
        $taskB = new ClosureTaskNode('taskB', fn() => $this->createDummyTask());
        $group  = new SimpleTaskGroupNode('group', [$taskA, $taskB]);
        $final  = new ClosureTaskNode('final', fn() => $this->createDummyTask(), ['taskA', 'taskB']);
        $root   = new SimpleTaskGroupNode('root', [$group, $final]);

        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 1);

        $first = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $first);
        $skipped = $queue->failTask($first->getId());
        $this->assertSame(['final'], $skipped);

        $second = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $second);
        $queue->completeTask($second->getId());

        $this->assertFalse($queue->getNextTask());
    }
}
