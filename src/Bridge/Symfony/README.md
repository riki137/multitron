# Symfony Integration

Register the `Multitron\Bridge\Symfony\MultitronExtension` or enable the provided bundle to make Multitron services available in the DI container.

The extension registers the following services and autowires them automatically:

- `Multitron\Tree\TaskTreeBuilderFactory`
- `Multitron\Orchestrator\TaskOrchestrator`
- `StreamIpc\NativeIpcPeer`
- `Multitron\Execution\ProcessExecutionFactory`
- `Multitron\Orchestrator\Output\TableOutputFactory`
- `Multitron\Execution\Handler\DefaultIpcHandlerRegistryFactory`
- `Multitron\Execution\Handler\MasterCache\MasterCacheServer`
- `Multitron\Execution\Handler\ProgressServer`

It also registers `Multitron\Console\MultitronWorkerCommand` and tags it as a console command, so your application can run worker processes.
