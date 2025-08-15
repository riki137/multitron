# Laravel Integration

Multitron ships with a service provider and console command that make it simple to run tasks inside a Laravel application.

## Install

```bash
composer require riki137/multitron
```

## Register the service provider

On Laravel 11 and newer add the provider to `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    Multitron\Bridge\Laravel\MultitronServiceProvider::class,
];
```

(For older Laravel versions register the provider in `config/app.php`.)

The provider exposes singletons such as `TaskTreeBuilderFactory` and
`TaskOrchestrator` and also registers the `Multitron\\Console\\MultitronWorkerCommand`
so worker processes can be launched.

## Define a command

Create a task and an Artisan command that extends
`Multitron\\Console\\TaskCommand` and accepts
`Multitron\\Console\\TaskCommandDeps`:

```php
namespace App\Console\Commands;

use App\Tasks\HelloTask;
use Multitron\Console\TaskCommand;
use Multitron\Console\TaskCommandDeps;
use Multitron\Tree\TaskTreeBuilder;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'multitron:demo')]
final class MultitronDemoCommand extends TaskCommand
{
    public function __construct(TaskCommandDeps $deps)
    {
        parent::__construct($deps);
    }

    public function getNodes(TaskTreeBuilder $builder): array
    {
        return [
            $builder->service(HelloTask::class),
        ];
    }
}
```

Register the command in `bootstrap/app.php`:

```php
return Application::configure()
    // ...
    ->withCommands([
        App\Console\Commands\MultitronDemoCommand::class,
    ])
    ->create();
```

Now you can run the command and Multitron will orchestrate your tasks:

```bash
php artisan multitron:demo
```
