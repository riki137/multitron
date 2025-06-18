# Nette Integration

Register the `Multitron\Bridge\Nette\MultitronExtension` in your `neon` configuration to autowire all required services:

```neon
extensions:
    multitron: Multitron\Bridge\Nette\MultitronExtension
```

The extension provides these services with autowiring enabled:

- `Multitron\Tree\TaskTreeBuilderFactory`
- `Multitron\Orchestrator\TaskOrchestrator`
- `StreamIpc\NativeIpcPeer`
- `Multitron\Execution\ProcessExecutionFactory`
- `Multitron\Orchestrator\Output\TableOutputFactory`
- `Multitron\Execution\Handler\DefaultIpcHandlerRegistryFactory`
- `Multitron\Execution\Handler\MasterCache\MasterCacheServer`
- `Multitron\Execution\Handler\ProgressServer`

After enabling the extension you can inject these services directly into your presenters, commands or other services.
