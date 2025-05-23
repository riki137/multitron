<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use LogicException;
use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
use Multitron\Orchestrator\TaskGraph;
use Multitron\Orchestrator\TaskList;
use Multitron\Tree\ClosureTaskNode;
use Multitron\Tree\SimpleTaskGroupNode;
use Multitron\Tree\TaskTreeBuilderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;

final class TaskGraphIntegrationTest extends TestCase
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

    public function testUnknownDependencyThrows(): void
    {
        $this->expectException(LogicException::class);
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);
        $task      = new ClosureTaskNode('task', fn() => $this->createDummyTask(), ['missing']);
        $root      = new SimpleTaskGroupNode('root', [$task]);
        $input     = new ArrayInput([]);
        $taskList  = new TaskList($factory, $root, $input);
        TaskGraph::buildFrom($taskList, $input);
    }
}
