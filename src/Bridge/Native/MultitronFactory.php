<?php

declare(strict_types=1);

namespace Multitron\Bridge\Native;

use InvalidArgumentException;
use Multitron\Comms\IpcAdapter;
use Multitron\Comms\NativeIpcAdapter;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\DefaultIpcHandlerRegistryFactory;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use Multitron\Execution\Handler\ProgressServer;
use Multitron\Execution\ProcessExecutionFactory;
use Multitron\Orchestrator\Output\ProgressOutputFactory;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\TaskTreeBuilderFactory;
use Psr\Container\ContainerInterface;
use StreamIpc\NativeIpcPeer;

final class MultitronFactory
{
    private ?WorkerCommand $workerCommand = null;

    private ?IpcAdapter $ipcAdapter = null;

    private ?TaskOrchestrator $taskOrchestrator = null;

    private ?ExecutionFactory $executionFactory = null;

    private ?ProgressOutputFactory $progressOutputFactory = null;

    private ?IpcHandlerRegistryFactory $ipcHandlerRegistryFactory = null;

    private float $workerTimeout = ProcessExecutionFactory::DEFAULT_TIMEOUT;

    private ?int $processBufferSize = null;

    /**
     * @var int<0,max>|null Default concurrency level for task execution. Null means amount of CPU threads.
     */
    private ?int $defaultConcurrency = null;

    private ?TaskCommandDeps $taskCommandDeps = null;

    private ?TaskTreeBuilderFactory $taskTreeBuilderFactory = null;

    public function __construct(private readonly ?ContainerInterface $container)
    {
    }

    public function getWorkerCommand(): WorkerCommand
    {
        return $this->workerCommand ??= new WorkerCommand($this->getIpcAdapter());
    }

    public function setWorkerCommand(?WorkerCommand $workerCommand): self
    {
        $this->workerCommand = $workerCommand;
        return $this;
    }

    public function getIpcAdapter(): IpcAdapter
    {
        return $this->ipcAdapter ??= new NativeIpcAdapter(new NativeIpcPeer());
    }

    public function setIpcAdapter(?IpcAdapter $ipcAdapter): MultitronFactory
    {
        $this->ipcAdapter = $ipcAdapter;
        return $this;
    }

    public function getTaskCommandDeps(): TaskCommandDeps
    {
        return $this->taskCommandDeps ??= new TaskCommandDeps(
            $this->getTaskTreeBuilderFactory(),
            $this->getTaskOrchestrator(),
            $this->defaultConcurrency
        );
    }

    public function setTaskCommandDeps(?TaskCommandDeps $taskCommandDeps): self
    {
        $this->taskCommandDeps = $taskCommandDeps;
        return $this;
    }

    public function getTaskTreeBuilderFactory(): TaskTreeBuilderFactory
    {
        return $this->taskTreeBuilderFactory ??= new TaskTreeBuilderFactory($this->container);
    }

    public function setTaskTreeBuilderFactory(?TaskTreeBuilderFactory $taskTreeBuilderFactory): self
    {
        $this->taskTreeBuilderFactory = $taskTreeBuilderFactory;
        return $this;
    }

    public function getTaskOrchestrator(): TaskOrchestrator
    {
        return $this->taskOrchestrator ??= new TaskOrchestrator(
            $this->getIpcAdapter()->getPeer(),
            $this->getExecutionFactory(),
            $this->getProgressOutputFactory(),
            $this->getIpcHandlerRegistryFactory()
        );
    }

    public function setTaskOrchestrator(?TaskOrchestrator $taskOrchestrator): self
    {
        $this->taskOrchestrator = $taskOrchestrator;
        return $this;
    }

    public function getExecutionFactory(): ExecutionFactory
    {
        if ($this->executionFactory === null) {
            $ipcPeer = $this->getIpcAdapter()->getPeer();
            if (!$ipcPeer instanceof NativeIpcPeer) {
                throw new InvalidArgumentException('ProcessExecutionFactory requires NativeIpcPeer. ' .
                    'Either use a different ExecutionFactory or set IpcAdapter to NativeIpcAdapter.');
            }

            return $this->executionFactory = new ProcessExecutionFactory(
                $ipcPeer,
                $this->getProcessBufferSize(),
                $this->getWorkerTimeout()
            );
        }

        return $this->executionFactory;
    }

    public function setExecutionFactory(?ExecutionFactory $executionFactory): self
    {
        $this->executionFactory = $executionFactory;
        return $this;
    }

    public function getProcessBufferSize(): ?int
    {
        return $this->processBufferSize;
    }

    public function setProcessBufferSize(?int $processBufferSize): self
    {
        $this->processBufferSize = $processBufferSize;
        return $this;
    }

    public function getWorkerTimeout(): float
    {
        return $this->workerTimeout;
    }

    public function setWorkerTimeout(float $workerTimeout): self
    {
        $this->workerTimeout = $workerTimeout;
        return $this;
    }

    public function getProgressOutputFactory(): ProgressOutputFactory
    {
        return $this->progressOutputFactory ??= new TableOutputFactory();
    }

    public function setProgressOutputFactory(?ProgressOutputFactory $progressOutputFactory): self
    {
        $this->progressOutputFactory = $progressOutputFactory;
        return $this;
    }

    public function getIpcHandlerRegistryFactory(): IpcHandlerRegistryFactory
    {
        return $this->ipcHandlerRegistryFactory ??= new DefaultIpcHandlerRegistryFactory(
            new MasterCacheServer(),
            new ProgressServer(),
        );
    }

    public function setIpcHandlerRegistryFactory(?IpcHandlerRegistryFactory $ipcHandlerRegistryFactory): self
    {
        $this->ipcHandlerRegistryFactory = $ipcHandlerRegistryFactory;
        return $this;
    }

    public function getDefaultConcurrency(): ?int
    {
        return $this->defaultConcurrency;
    }

    public function setDefaultConcurrency(?int $defaultConcurrency): self
    {
        if ($defaultConcurrency < 0) {
            throw new InvalidArgumentException('Default concurrency must be a positive integer or null.');
        }
        $this->defaultConcurrency = $defaultConcurrency;
        return $this;
    }
}
