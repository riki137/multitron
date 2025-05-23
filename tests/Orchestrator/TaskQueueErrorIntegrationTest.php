<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use LogicException;
use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskQueue;
use Multitron\Tree\ClosureTaskNode;
use Multitron\Tree\SimpleTaskGroupNode;
use Multitron\Tree\TaskTreeBuilderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;

final class TaskQueueErrorIntegrationTest extends TestCase
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

    public function testCannotConstructWithZeroConcurrency(): void
    {
        $this->expectException(LogicException::class);
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);
        $task      = new ClosureTaskNode('t', fn() => $this->createDummyTask());
        $root      = new SimpleTaskGroupNode('root', [$task]);
        $input     = new ArrayInput([]);
        $taskList  = new TaskList($factory, $root, $input);
        new TaskQueue($taskList, $input, 0);
    }

    public function testCompleteOrFailNonRunningTaskThrows(): void
    {
        $this->expectException(LogicException::class);
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);
        $task      = new ClosureTaskNode('t', fn() => $this->createDummyTask());
        $root      = new SimpleTaskGroupNode('root', [$task]);
        $input     = new ArrayInput([]);
        $taskList  = new TaskList($factory, $root, $input);
        $queue     = new TaskQueue($taskList, $input, 1);
        $queue->completeTask('t');
    }

    public function testFailNonRunningTaskThrows(): void
    {
        $this->expectException(LogicException::class);
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);
        $task      = new ClosureTaskNode('t', fn() => $this->createDummyTask());
        $root      = new SimpleTaskGroupNode('root', [$task]);
        $input     = new ArrayInput([]);
        $taskList  = new TaskList($factory, $root, $input);
        $queue     = new TaskQueue($taskList, $input, 1);
        $queue->failTask('t');
    }
}
