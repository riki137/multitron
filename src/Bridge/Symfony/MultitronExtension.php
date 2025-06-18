<?php

declare(strict_types=1);

namespace Multitron\Bridge\Symfony;

use Multitron\Execution\Handler\DefaultIpcHandlerRegistryFactory;
use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use Multitron\Execution\Handler\ProgressServer;
use Multitron\Execution\ProcessExecutionFactory;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\TaskTreeBuilderFactory;
use Psr\Container\ContainerInterface;
use StreamIpc\NativeIpcPeer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Multitron\Console\MultitronWorkerCommand;

final class MultitronExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->setAlias(ContainerInterface::class, 'service_container');

        $container->setDefinition(TaskTreeBuilderFactory::class, new Definition(TaskTreeBuilderFactory::class, [new Reference('service_container')]))
            ->setAutowired(true)
            ->setPublic(false);

        $container->register(NativeIpcPeer::class)
            ->setAutowired(true)
            ->setPublic(false);

        $container->register(MasterCacheServer::class)
            ->setAutowired(true)
            ->setPublic(false);

        $container->register(ProgressServer::class)
            ->setAutowired(true)
            ->setPublic(false);

        $container->register(DefaultIpcHandlerRegistryFactory::class)
            ->setAutowired(true)
            ->setPublic(false);

        $container->register(TableOutputFactory::class)
            ->setAutowired(true)
            ->setPublic(false);

        $container->register(ProcessExecutionFactory::class)
            ->setAutowired(true)
            ->setPublic(false);

        $container->register(TaskOrchestrator::class)
            ->setAutowired(true)
            ->setPublic(false);

        $container->register(MultitronWorkerCommand::class)
            ->addTag('console.command')
            ->setAutowired(true)
            ->setPublic(true);
    }
}
