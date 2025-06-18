<?php

declare(strict_types=1);

namespace Multitron\Bridge\Nette;

use Multitron\Execution\Handler\DefaultIpcHandlerRegistryFactory;
use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use Multitron\Execution\Handler\ProgressServer;
use Multitron\Execution\ProcessExecutionFactory;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\TaskTreeBuilderFactory;
use Nette\DI\CompilerExtension;
use Psr\Container\ContainerInterface;
use StreamIpc\NativeIpcPeer;

final class MultitronExtension extends CompilerExtension
{
    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('taskTreeBuilderFactory'))
            ->setType(TaskTreeBuilderFactory::class);

        $builder->addDefinition($this->prefix('nativeIpcPeer'))
            ->setType(NativeIpcPeer::class);

        $builder->addDefinition($this->prefix('masterCacheServer'))
            ->setType(MasterCacheServer::class);

        $builder->addDefinition($this->prefix('progressServer'))
            ->setType(ProgressServer::class);

        $builder->addDefinition($this->prefix('handlerFactory'))
            ->setType(DefaultIpcHandlerRegistryFactory::class);

        $builder->addDefinition($this->prefix('tableOutputFactory'))
            ->setType(TableOutputFactory::class);

        $builder->addDefinition($this->prefix('executionFactory'))
            ->setType(ProcessExecutionFactory::class);

        $builder->addDefinition($this->prefix('taskOrchestrator'))
            ->setType(TaskOrchestrator::class);
    }
}
