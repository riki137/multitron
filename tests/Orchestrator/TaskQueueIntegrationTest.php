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

final class TaskQueueIntegrationTest extends TestCase
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

    public function testQueueRespectsDependenciesAndConcurrency(): void
    {
        $container = $this->createContainer();
        $builder   = new TaskTreeBuilder($container);

        $task1 = new ClosureTaskNode('task1', fn() => $this->createDummyTask());
        $task2 = new ClosureTaskNode('task2', fn() => $this->createDummyTask(), ['task1']);
        $task3 = new ClosureTaskNode('task3', fn() => $this->createDummyTask(), ['task2']);

        $root = new SimpleTaskGroupNode('root', [$task1, $task2, $task3]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($container, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 1);

        $next = $queue->getNextTask();
        $this->assertSame('task1', $next->getId());
        $this->assertTrue($queue->getNextTask()); // concurrency limit reached
        $queue->completeTask('task1');

        $next = $queue->getNextTask();
        $this->assertSame('task2', $next->getId());
        $queue->completeTask('task2');

        $next = $queue->getNextTask();
        $this->assertSame('task3', $next->getId());
        $queue->completeTask('task3');

        $this->assertFalse($queue->getNextTask());
    }

    public function testFailTaskSkipsDependents(): void
    {
        $container = $this->createContainer();
        $builder   = new TaskTreeBuilder($container);

        $task1 = new ClosureTaskNode('task1', fn() => $this->createDummyTask());
        $task2 = new ClosureTaskNode('task2', fn() => $this->createDummyTask(), ['task1']);
        $task3 = new ClosureTaskNode('task3', fn() => $this->createDummyTask(), ['task2']);

        $root  = new SimpleTaskGroupNode('root', [$task1, $task2, $task3]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($container, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 2);

        $next = $queue->getNextTask();
        $this->assertSame('task1', $next->getId());
        $queue->completeTask('task1');

        $next = $queue->getNextTask();
        $this->assertSame('task2', $next->getId());

        $skipped = $queue->failTask('task2');
        $this->assertSame(['task3'], $skipped);
        $this->assertFalse($queue->getNextTask());
    }
}

