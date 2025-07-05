<?php

declare(strict_types=1);

namespace Multitron\Bridge\Laravel;

use Illuminate\Support\ServiceProvider;
use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use Psr\Container\ContainerInterface;

final class MultitronServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MultitronFactory::class);

        $this->app->singleton(
            TaskCommandDeps::class,
            fn(ContainerInterface $c) => $c->get(MultitronFactory::class)->getCommandDeps()
        );

        $this->app->singleton(
            WorkerCommand::class,
            fn(ContainerInterface $c) => $c->get(MultitronFactory::class)->getWorkerCommand()
        );

        $this->commands([WorkerCommand::class]);
    }
}
