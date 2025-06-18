# Nette Integration

## Installation

First, require the necessary packages:

```bash
composer require riki137/mulitrtron contributte/psr11-container-interface
```

## Configuration

Register both the PSR-11 extension and the Multitron extension in your `neon` configuration to autowire all required services:

```neon
extensions:
    psr11: Contributte\Psr11\DI\Psr11ContainerExtension
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
