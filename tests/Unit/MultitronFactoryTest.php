<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use InvalidArgumentException;
use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Comms\NativeIpcAdapter;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\ProcessExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Orchestrator\Output\ProgressOutputFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tests\Mocks\AppContainer;
use Multitron\Tree\TaskTreeBuilderFactory;
use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;

final class MultitronFactoryTest extends TestCase
{
    private function createContainer(): AppContainer
    {
        return new AppContainer();
    }

    public function testAllSettersAndDefaults(): void
    {
        $factory = new MultitronFactory($this->createContainer());

        $ipc = new NativeIpcAdapter(new NativeIpcPeer());
        $factory->setIpcAdapter($ipc);
        $this->assertSame($ipc, $factory->getIpcAdapter());

        $worker = new WorkerCommand($factory->getIpcAdapter());
        $factory->setWorkerCommand($worker);
        $this->assertSame($worker, $factory->getWorkerCommand());

        $exec = $this->createMock(ExecutionFactory::class);
        $outFactory = $this->createMock(ProgressOutputFactory::class);
        $regFactory = $this->createMock(IpcHandlerRegistryFactory::class);

        $orch = new TaskOrchestrator(
            $factory->getIpcAdapter()->getPeer(),
            $exec,
            $outFactory,
            $regFactory,
        );
        $factory->setTaskOrchestrator($orch);
        $this->assertSame($orch, $factory->getTaskOrchestrator());

        $factory->setExecutionFactory($exec);
        $this->assertSame($exec, $factory->getExecutionFactory());

        $factory->setProgressOutputFactory($outFactory);
        $this->assertSame($outFactory, $factory->getProgressOutputFactory());

        $factory->setIpcHandlerRegistryFactory($regFactory);
        $this->assertSame($regFactory, $factory->getIpcHandlerRegistryFactory());

        $treeFactory = new TaskTreeBuilderFactory($this->createContainer());
        $factory->setTaskTreeBuilderFactory($treeFactory);
        $this->assertSame($treeFactory, $factory->getTaskTreeBuilderFactory());

        $deps = new TaskCommandDeps($treeFactory, $orch, 7);
        $factory->setTaskCommandDeps($deps);
        $this->assertSame($deps, $factory->getTaskCommandDeps());

        $this->assertNull($factory->getProcessBufferSize());
        $this->assertSame(ProcessExecutionFactory::DEFAULT_TIMEOUT, $factory->getWorkerTimeout());

        $factory->setProcessBufferSize(5);
        $factory->setWorkerTimeout(2.5);
        $this->assertSame(5, $factory->getProcessBufferSize());
        $this->assertSame(2.5, $factory->getWorkerTimeout());

        $factory->setDefaultConcurrency(3);
        $this->assertSame(3, $factory->getDefaultConcurrency());
    }

    public function testSetDefaultConcurrencyRejectsNegative(): void
    {
        $factory = new MultitronFactory($this->createContainer());
        $this->expectException(InvalidArgumentException::class);
        $factory->setDefaultConcurrency(-1);
    }
}

