# Laravel Integration

Register the `Multitron\Bridge\Laravel\MultitronServiceProvider` in your `config/app.php` to autowire all Multitron services:

```php
'providers' => [
    // ...
    Multitron\Bridge\Laravel\MultitronServiceProvider::class,
],
```

The service provider makes these singletons available:

- `Multitron\Tree\TaskTreeBuilderFactory`
- `Multitron\Orchestrator\TaskOrchestrator`
- `StreamIpc\NativeIpcPeer`
- `Multitron\Execution\ProcessExecutionFactory`
- `Multitron\Orchestrator\Output\TableOutputFactory`
- `Multitron\Execution\Handler\DefaultIpcHandlerRegistryFactory`
- `Multitron\Execution\Handler\MasterCache\MasterCacheServer`
- `Multitron\Execution\Handler\ProgressServer`

The provider also registers `Multitron\Console\MultitronWorkerCommand` as an Artisan command so your application can launch worker processes.
