<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskQueue;
use Multitron\Tree\ClosureTaskNode;
use Multitron\Tree\SimpleTaskGroupNode;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeBuilderFactory;
use Multitron\Tree\TaskLeafNode;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;

final class TaskQueueGroupIntegrationTest extends TestCase
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

    public function testGroupNodesAreExpandedAndDependentsReady(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);
        $builder   = $factory->create();

        $task1 = new ClosureTaskNode('task1', fn() => $this->createDummyTask());
        $task2 = new ClosureTaskNode('task2', fn() => $this->createDummyTask(), ['group']);
        $task3 = new ClosureTaskNode('task3', fn() => $this->createDummyTask(), ['group']);

        $group = new SimpleTaskGroupNode('group', [$task2], ['task1']);
        $root  = new SimpleTaskGroupNode('root', [$task1, $group, $task3]);

        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 1);

        $next = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $next);
        $this->assertSame('task1', $next->getId());
        $queue->completeTask('task1');

        $first = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $first);
        $this->assertContains($first->getId(), ['task2', 'task3']);
        $queue->completeTask($first->getId());

        $second = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $second);
        $expected = $first->getId() === 'task2' ? 'task3' : 'task2';
        $this->assertSame($expected, $second->getId());
        $queue->completeTask($expected);

        $this->assertFalse($queue->getNextTask());
    }
}
