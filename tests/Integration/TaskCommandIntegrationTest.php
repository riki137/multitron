<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Comms\NativeIpcAdapter;
use Multitron\Console\TaskCommand;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tests\Mocks\AppContainer;
use Multitron\Tests\Mocks\DummyExecutionFactory;
use Multitron\Tests\Mocks\DummyIpcHandlerRegistryFactory;
use Multitron\Tests\Mocks\DummyTask;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeBuilderFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StreamIpc\IpcPeer;
use StreamIpc\NativeIpcPeer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class TaskCommandIntegrationTest extends TestCase
{
    private function createDeps(IpcPeer $peer): TaskCommandDeps
    {
        $execFactory = new DummyExecutionFactory($peer);
        $registryFactory = new DummyIpcHandlerRegistryFactory();
        $builderFactory = new TaskTreeBuilderFactory(new AppContainer());
        $outputFactory = new TableOutputFactory();
        $orchestrator = new TaskOrchestrator($peer, $execFactory, $outputFactory, $registryFactory);
        return new TaskCommandDeps($builderFactory, $orchestrator);
    }

    public function testGetTaskListFiltersByPattern(): void
    {
        $peer = new NativeIpcPeer();
        $deps = $this->createDeps($peer);
        $command = new #[AsCommand('demo')] class($deps) extends TaskCommand {
            public function getNodes(TaskTreeBuilder $builder): array
            {
                return [
                    $builder->task('first', fn() => new DummyTask()),
                    $builder->task('second', fn() => new DummyTask()),
                ];
            }
        };

        $list = $command->getTaskList(new ArrayInput(['pattern' => 'first'], $command->getDefinition()));
        $nodes = $list->toArray();
        $this->assertCount(1, $nodes);
        $this->assertArrayHasKey('first', $nodes);
    }

    public function testExecuteFailsWhenWorkerMissing(): void
    {
        $peer = new NativeIpcPeer();
        $deps = $this->createDeps($peer);
        $command = new #[AsCommand('demo')] class($deps) extends TaskCommand {
            public function getNodes(TaskTreeBuilder $builder): array
            {
                return [];
            }
        };

        $app = new Application();
        $app->add($command);

        $this->expectException(RuntimeException::class);
        $command->run(new ArrayInput([], $command->getDefinition()), new BufferedOutput());
    }

    public function testExecuteRunsTasks(): void
    {
        $peer = new NativeIpcPeer();
        $adapter = new NativeIpcAdapter($peer);
        $deps = $this->createDeps($peer);
        $command = new #[AsCommand('demo')] class($deps) extends TaskCommand {
            public function getNodes(TaskTreeBuilder $builder): array
            {
                return [
                    $builder->task('t', fn() => new DummyTask()),
                ];
            }
        };

        $app = new Application();
        $worker = new WorkerCommand($adapter);
        $app->add($worker);
        $app->add($command);

        $result = $command->run(new ArrayInput([], $command->getDefinition()), new BufferedOutput());
        $this->assertSame(0, $result);
    }
}

