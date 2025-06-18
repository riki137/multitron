<?php

declare(strict_types=1);

namespace Multitron\Bridge\Laravel;

use Illuminate\Support\ServiceProvider;
use Multitron\Console\MultitronWorkerCommand;
use Multitron\Execution\Handler\DefaultIpcHandlerRegistryFactory;
use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use Multitron\Execution\Handler\ProgressServer;
use Multitron\Execution\ProcessExecutionFactory;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\TaskTreeBuilderFactory;
use Psr\Container\ContainerInterface;
use StreamIpc\NativeIpcPeer;

final class MultitronServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TaskTreeBuilderFactory::class, fn (ContainerInterface $app) => new TaskTreeBuilderFactory($app));
        $this->app->singleton(NativeIpcPeer::class);
        $this->app->singleton(MasterCacheServer::class);
        $this->app->singleton(ProgressServer::class);
        $this->app->singleton(DefaultIpcHandlerRegistryFactory::class);
        $this->app->singleton(TableOutputFactory::class);
        $this->app->singleton(ProcessExecutionFactory::class);
        $this->app->singleton(TaskOrchestrator::class);
        $this->app->singleton(MultitronWorkerCommand::class);

        $this->commands([MultitronWorkerCommand::class]);
    }
}
