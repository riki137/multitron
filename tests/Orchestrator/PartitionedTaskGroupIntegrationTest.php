<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use Multitron\Comms\TaskCommunicator;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskQueue;
use Multitron\Tree\TaskTreeBuilderFactory;
use Multitron\Tree\Partition\PartitionedTask;
use Multitron\Tree\PartitionedTaskGroupNode;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;

final class DummyPartitionedTask extends PartitionedTask
{
    public function execute(TaskCommunicator $comm): void {}

    public function getInfo(): array
    {
        return [$this->partitionIndex, $this->partitionCount];
    }
}

final class PartitionedTaskGroupIntegrationTest extends TestCase
{
    private function createContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id) { return null; }
            public function has(string $id): bool { return false; }
        };
    }

    public function testPartitionedTasksAreCreatedWithCorrectIndices(): void
    {
        $container     = $this->createContainer();
        $builderFactory = new TaskTreeBuilderFactory($container);
        $group         = new PartitionedTaskGroupNode('build', 3, fn() => new DummyPartitionedTask(), []);
        $input     = new ArrayInput([]);

        $taskList = new TaskList($builderFactory, $group, $input);
        $queue    = new TaskQueue($taskList, $input, 3);

        $ids = [];
        $info = [];
        for ($i = 0; $i < 3; $i++) {
            $node = $queue->getNextTask();
            $ids[] = $node->getId();
            $factory = $node->getFactory($input);
            $task = $factory();
            $info[] = $task->getInfo();
            $queue->completeTask($node->getId());
        }

        sort($ids);
        sort($info);
        $this->assertSame(['build 1/3', 'build 2/3', 'build 3/3'], $ids);
        $this->assertSame([[0,3], [1,3], [2,3]], $info);
        $this->assertFalse($queue->getNextTask());
    }
}
