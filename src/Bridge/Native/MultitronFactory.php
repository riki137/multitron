<?php

declare(strict_types=1);

namespace Multitron\Bridge\Native;

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

    private ?NativeIpcPeer $ipcPeer = null;

    private ?TaskOrchestrator $taskOrchestrator = null;

    private ?ExecutionFactory $executionFactory = null;

    private ?ProgressOutputFactory $progressOutputFactory = null;

    private ?IpcHandlerRegistryFactory $ipcHandlerRegistryFactory = null;

    private float $workerTimeout = ProcessExecutionFactory::DEFAULT_TIMEOUT;

    private ?int $processBufferSize = null;

    private ?TaskCommandDeps $taskCommandDeps = null;

    private ?TaskTreeBuilderFactory $taskTreeBuilderFactory = null;

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function getWorkerCommand(): WorkerCommand
    {
        return $this->workerCommand ??= new WorkerCommand($this->getIpcPeer());
    }

    public function getIpcPeer(): NativeIpcPeer
    {
        return $this->ipcPeer ??= new NativeIpcPeer();
    }

    public function getTaskOrchestrator(): TaskOrchestrator
    {
        return $this->taskOrchestrator ??= new TaskOrchestrator(
            $this->getIpcPeer(),
            $this->getExecutionFactory(),
            $this->getProgressOutputFactory(),
            $this->getIpcHandlerRegistryFactory()
        );
    }

    public function getProcessBufferSize(): ?int
    {
        return $this->processBufferSize;
    }

    public function getWorkerTimeout(): float
    {
        return $this->workerTimeout;
    }

    public function getExecutionFactory(): ExecutionFactory
    {
        return $this->executionFactory ??= new ProcessExecutionFactory(
            $this->getIpcPeer(),
            $this->getProcessBufferSize(),
            $this->getWorkerTimeout()
        );
    }

    public function getProgressOutputFactory(): ProgressOutputFactory
    {
        return $this->progressOutputFactory ??= new TableOutputFactory();
    }

    public function getIpcHandlerRegistryFactory(): IpcHandlerRegistryFactory
    {
        return $this->ipcHandlerRegistryFactory ??= new DefaultIpcHandlerRegistryFactory(
            new MasterCacheServer(),
            new ProgressServer(),
        );
    }

    public function getTaskCommandDeps(): TaskCommandDeps
    {
        return $this->taskCommandDeps ??= new TaskCommandDeps(
            $this->getTaskTreeBuilderFactory(),
            $this->getTaskOrchestrator(),
        );
    }

    public function getTaskTreeBuilderFactory(): TaskTreeBuilderFactory
    {
        return $this->taskTreeBuilderFactory ??= new TaskTreeBuilderFactory($this->container);
    }

    public function setWorkerCommand(?WorkerCommand $workerCommand): self
    {
        $this->workerCommand = $workerCommand;
        return $this;
    }

    public function setIpcPeer(?NativeIpcPeer $ipcPeer): self
    {
        $this->ipcPeer = $ipcPeer;
        return $this;
    }

    public function setTaskOrchestrator(?TaskOrchestrator $taskOrchestrator): self
    {
        $this->taskOrchestrator = $taskOrchestrator;
        return $this;
    }

    public function setExecutionFactory(?ExecutionFactory $executionFactory): self
    {
        $this->executionFactory = $executionFactory;
        return $this;
    }

    public function setProgressOutputFactory(?ProgressOutputFactory $progressOutputFactory): self
    {
        $this->progressOutputFactory = $progressOutputFactory;
        return $this;
    }

    public function setIpcHandlerRegistryFactory(?IpcHandlerRegistryFactory $ipcHandlerRegistryFactory): self
    {
        $this->ipcHandlerRegistryFactory = $ipcHandlerRegistryFactory;
        return $this;
    }

    public function setWorkerTimeout(float $workerTimeout): self
    {
        $this->workerTimeout = $workerTimeout;
        return $this;
    }

    public function setProcessBufferSize(?int $processBufferSize): self
    {
        $this->processBufferSize = $processBufferSize;
        return $this;
    }

    public function setTaskCommandDeps(?TaskCommandDeps $taskCommandDeps): self
    {
        $this->taskCommandDeps = $taskCommandDeps;
        return $this;
    }

    public function setTaskTreeBuilderFactory(?TaskTreeBuilderFactory $taskTreeBuilderFactory): self
    {
        $this->taskTreeBuilderFactory = $taskTreeBuilderFactory;
        return $this;
    }
}
