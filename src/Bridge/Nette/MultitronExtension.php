<?php

declare(strict_types=1);

namespace Multitron\Bridge\Nette;

use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use Nette\DI\CompilerExtension;

final class MultitronExtension extends CompilerExtension
{
    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();

        $factory = $builder->addDefinition($this->prefix('factory'))
            ->setType(MultitronFactory::class)
            ->setCreator(MultitronFactory::class);

        $builder->addDefinition($this->prefix('commandDeps'))
            ->setType(TaskCommandDeps::class)
            ->setCreator([$factory, 'getCommandDeps']);

        $builder->addDefinition($this->prefix('workerCommand'))
            ->setType(WorkerCommand::class)
            ->setFactory([$factory, 'getWorkerCommand']);
    }
}
