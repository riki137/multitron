<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
use Multitron\Orchestrator\TaskList;
use Multitron\Tree\ClosureTaskNode;
use Multitron\Tree\PatternTaskFilterNode;
use Multitron\Tree\SimpleTaskGroupNode;
use Multitron\Tree\TaskTreeBuilderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;

final class TaskFilterNodeIntegrationTest extends TestCase
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

    public function testFilterNodeFiltersTasksById(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        $taskA  = new ClosureTaskNode('taskA1', fn() => $this->createDummyTask());
        $taskB  = new ClosureTaskNode('taskB1', fn() => $this->createDummyTask());
        $filter = new PatternTaskFilterNode('filter', 'taskA*', [$taskA, $taskB]);

        $root  = new SimpleTaskGroupNode('root', [$filter]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $nodes    = $taskList->getNodes();

        $this->assertArrayHasKey('taskA1', $nodes);
        $this->assertArrayNotHasKey('taskB1', $nodes);
    }

    public function testFilterNodeAllowsGroupByPattern(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        $a1     = new ClosureTaskNode('a1', fn() => $this->createDummyTask());
        $groupA = new SimpleTaskGroupNode('groupA', [$a1]);

        $b1     = new ClosureTaskNode('b1', fn() => $this->createDummyTask());
        $groupB = new SimpleTaskGroupNode('groupB', [$b1]);

        $outside = new ClosureTaskNode('outside', fn() => $this->createDummyTask());

        $filter = new PatternTaskFilterNode('filter', 'groupA', [$groupA, $groupB]);

        $root  = new SimpleTaskGroupNode('root', [$filter, $outside]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $nodes    = $taskList->getNodes();

        $this->assertArrayHasKey('groupA', $nodes);
        $this->assertArrayHasKey('a1', $nodes);
        $this->assertArrayHasKey('outside', $nodes);
        $this->assertArrayNotHasKey('groupB', $nodes);
        $this->assertArrayNotHasKey('b1', $nodes);
    }
}

