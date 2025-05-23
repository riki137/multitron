<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskQueue;
use Multitron\Tree\ClosureTaskNode;
use Multitron\Tree\SimpleTaskGroupNode;
use Multitron\Tree\TaskTreeBuilderFactory;
use Multitron\Tree\TaskLeafNode;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;

final class GroupIdDependencyIntegrationTest extends TestCase
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

    public function testTaskCanDependOnGroupId(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        $a1 = new ClosureTaskNode('a1', fn() => $this->createDummyTask(), ['groupA']);
        $a2 = new ClosureTaskNode('a2', fn() => $this->createDummyTask(), ['groupA']);
        $groupA = new SimpleTaskGroupNode('groupA', [$a1, $a2]);

        $b1 = new ClosureTaskNode('b1', fn() => $this->createDummyTask(), ['groupB']);
        $groupB = new SimpleTaskGroupNode('groupB', [$b1]);

        $final = new ClosureTaskNode('final', fn() => $this->createDummyTask(), ['groupA', 'groupB']);

        $root = new SimpleTaskGroupNode('root', [$groupA, $groupB, $final]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 1);

        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $task = $queue->getNextTask();
            $this->assertInstanceOf(TaskLeafNode::class, $task);
            $ids[] = $task->getId();
            $queue->completeTask($task->getId());
        }

        sort($ids);
        $this->assertSame(['a1', 'a2', 'b1'], $ids);

        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $this->assertSame('final', $task->getId());
        $queue->completeTask('final');

        $this->assertFalse($queue->getNextTask());
    }
}
