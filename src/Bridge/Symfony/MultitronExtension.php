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
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->setDefinition('multitron.factory', new Definition(MultitronFactory::class));

        $container->setDefinition('multitron.command_deps',
            (new Definition(TaskCommandDeps::class))
                ->setFactory([new Reference('multitron.factory'), 'getCommandDeps'])
        );

        $container->setDefinition(
            'multitron.worker_command',
            (new Definition(WorkerCommand::class))
                ->setFactory([new Reference('multitron.factory'), 'getWorkerCommand'])
        );
    }
}
