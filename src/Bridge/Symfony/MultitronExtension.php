<?php

declare(strict_types=1);

namespace Multitron\Bridge\Symfony;

use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class MultitronExtension extends Extension
{
    public const ID_FACTORY = 'multitron.factory';
    public const ID_TASK_COMMAND_DEPS = 'multitron.task_command_deps';
    public const ID_WORKER_COMMAND = 'multitron.worker_command';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->setDefinition(MultitronFactory::class, new Definition(MultitronFactory::class))
            ->setAutowired(true);
        $container->setAlias(self::ID_FACTORY, MultitronFactory::class);

        $container->setDefinition(
            TaskCommandDeps::class,
            (new Definition(TaskCommandDeps::class))
                ->setFactory([new Reference(MultitronFactory::class), 'getTaskCommandDeps'])
                ->setAutowired(true)
        );
        $container->setAlias(self::ID_TASK_COMMAND_DEPS, TaskCommandDeps::class);

        $container->setDefinition(
            WorkerCommand::class,
            (new Definition(WorkerCommand::class))
                ->setFactory([new Reference(MultitronFactory::class), 'getWorkerCommand'])
                ->addTag('console.command')
        );
        $container->setAlias(self::ID_WORKER_COMMAND, WorkerCommand::class);
    }
}
