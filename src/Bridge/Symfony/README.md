# Symfony Integration

The Symfony bridge exposes Multitron services through a container extension so
that you can wire them like any other service.

## Register the Extension

Add the `MultitronExtension` to your kernel so the services become available in
the container:

```php
// src/Kernel.php

use Multitron\Bridge\Symfony\MultitronExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->registerExtension(new MultitronExtension());
    }
}
```

The extension registers `TaskCommandDeps` for autowiring and exposes the
`multitron:worker` console command automatically.

## Autowire `TaskCommandDeps`

Your commands can depend on `TaskCommandDeps` and extend `TaskCommand`:

```php
use Multitron\Console\TaskCommand;
use Multitron\Console\TaskCommandDeps;
use Multitron\Tree\TaskTreeBuilder;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'app:tasks')]
final class AppTaskCommand extends TaskCommand
{
    public function __construct(TaskCommandDeps $deps)
    {
        parent::__construct($deps);
    }

    public function getNodes(TaskTreeBuilder $b): array
    {
        return [
            // define your tasks here
        ];
    }
}
```

Run `bin/console app:tasks` to execute your tasks. The `multitron:worker`
command is available automatically to handle worker processes.

