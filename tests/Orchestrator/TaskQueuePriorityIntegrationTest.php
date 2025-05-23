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
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;

final class TaskQueuePriorityIntegrationTest extends TestCase
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

    public function testHigherPriorityTaskRunsFirst(): void
    {
        $container = $this->createContainer();
        $builder   = new TaskTreeBuilder($container);

        $taskA = new ClosureTaskNode('taskA', fn() => $this->createDummyTask());
        $taskB = new ClosureTaskNode('taskB', fn() => $this->createDummyTask(), ['taskA']);
        $taskC = new ClosureTaskNode('taskC', fn() => $this->createDummyTask(), ['taskA']);
        $taskD = new ClosureTaskNode('taskD', fn() => $this->createDummyTask());

        $root = new SimpleTaskGroupNode('root', [$taskA, $taskB, $taskC, $taskD]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($container, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 1);

        $next = $queue->getNextTask();
        $this->assertSame('taskA', $next->getId());
    }
}
