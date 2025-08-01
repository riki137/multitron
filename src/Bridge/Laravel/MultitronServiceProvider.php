<?php

declare(strict_types=1);

namespace Multitron\Bridge\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;

final class MultitronServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MultitronFactory::class);

        $this->app->singleton(
            TaskCommandDeps::class,
            fn (Container $c) => $c->make(MultitronFactory::class)->getTaskCommandDeps()
        );

        $this->app->singleton(
            WorkerCommand::class,
            fn (Container $c) => $c->make(MultitronFactory::class)->getWorkerCommand()
        );

        $this->commands([WorkerCommand::class]);
    }
}
