<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Orchestrator\Output\ProgressOutputFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\TaskTreeBuilderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class MultitronFactoryIntegrationTest extends TestCase
{
    private function createContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): object
            {
                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }
        };
    }

    public function testFactoryCreatesAndCachesDefaultServices(): void
    {
        $factory = new MultitronFactory($this->createContainer());

        $worker = $factory->getWorkerCommand();
        $this->assertInstanceOf(WorkerCommand::class, $worker);
        $this->assertSame($worker, $factory->getWorkerCommand());

        $ipc = $factory->getIpcPeer();
        $this->assertSame($ipc, $factory->getIpcPeer());

        $orch = $factory->getTaskOrchestrator();
        $this->assertInstanceOf(TaskOrchestrator::class, $orch);
        $this->assertSame($orch, $factory->getTaskOrchestrator());

        $execFactory = $factory->getExecutionFactory();
        $this->assertInstanceOf(ExecutionFactory::class, $execFactory);
        $this->assertSame($execFactory, $factory->getExecutionFactory());

        $outFactory = $factory->getProgressOutputFactory();
        $this->assertInstanceOf(ProgressOutputFactory::class, $outFactory);
        $this->assertSame($outFactory, $factory->getProgressOutputFactory());

        $handlerFactory = $factory->getIpcHandlerRegistryFactory();
        $this->assertInstanceOf(IpcHandlerRegistryFactory::class, $handlerFactory);
        $this->assertSame($handlerFactory, $factory->getIpcHandlerRegistryFactory());

        $deps = $factory->getTaskCommandDeps();
        $this->assertInstanceOf(TaskCommandDeps::class, $deps);
        $this->assertSame($deps, $factory->getTaskCommandDeps());

        $treeFactory = $factory->getTaskTreeBuilderFactory();
        $this->assertInstanceOf(TaskTreeBuilderFactory::class, $treeFactory);
        $this->assertSame($treeFactory, $factory->getTaskTreeBuilderFactory());
    }

    public function testSettersOverrideDefaults(): void
    {
        $factory = new MultitronFactory($this->createContainer());

        $worker = new WorkerCommand($factory->getIpcPeer());
        $factory->setWorkerCommand($worker);
        $this->assertSame($worker, $factory->getWorkerCommand());

        $factory->setProcessBufferSize(10);
        $factory->setWorkerTimeout(123.0);
        $this->assertSame(10, $factory->getProcessBufferSize());
        $this->assertSame(123.0, $factory->getWorkerTimeout());
    }
}
