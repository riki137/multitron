# Native PHP Integration

## Requirements

* **PHP ≥ 8.2** with `ext-pcntl` and `ext-mbstring`
* A PSR-11 container (any implementation; only needed if you use `service()` tasks)
* A CLI entrypoint (e.g. `bin/multitron.php`)

---

## Installation

```bash
composer require riki137/multitron psr/container symfony/console
```

---

## Console Script

Create (or update) your CLI script—here `bin/multitron.php`. It must:

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Comms\TaskCommunicator;
use Multitron\Console\TaskCommand;
use Multitron\Execution\Task;
use Multitron\Tree\TaskTreeBuilder;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;

// If you use a framework, you probably already have a container instance
class AppContainer implements ContainerInterface {
    public function get(string $id)
    {
        return new $id();
    }

    public function has(string $id): bool
    {
        return class_exists($id);
    }
};

$factory = new MultitronFactory(new AppContainer());

$app = new Application();
$app->add($factory->getWorkerCommand()); // must be in the same script as the App commands

#[AsCommand('app:tasks')]
class AppTaskCommand extends TaskCommand
{
    public function getNodes(TaskTreeBuilder $b): array
    {
        return [
            // Example closure-based task:
            $b->task('say-hello', fn() => new class implements Task {
                public function execute(TaskCommunicator $comm): void
                {
                    $comm->log('👋 Hello from Multitron!');
                }
            }),
            // Add more tasks, use $b->service(), $b->group(), $b->partitioned(), etc.
        ];
    }
}

$app->add(new AppTaskCommand($factory->getTaskCommandDeps()));

$app->run();
```

Make it executable:

```bash
chmod +x bin/multitron.php
```

---

## Customization

The `MultitronFactory` allows you to customize various aspects of Multitron's behavior. You can create your own instances of services and set them on the factory before you create the commands.

```php
$factory = new MultitronFactory($container);

// Example: Use a different progress output factory
// $factory->setProgressOutputFactory(new MyAwesomeProgressOutputFactory());

// Example: Change the worker timeout
$factory->setWorkerTimeout(300.0);

$app = new \Symfony\Component\Console\Application();
// Now create the commands with the customized factory
$app->add(new AppTaskCommand($factory->getTaskCommandDeps()));
$app->add($factory->getWorkerCommand());

$app->run();
```

---

## Running

* **Run tasks**
  ```bash
  ./bin/multitron.php app:tasks
  ```
* **Filter by pattern**

  ```bash
  ./bin/multitron.php app:tasks Test%
  ```
* **Limit concurrency**

  ```bash
  ./bin/multitron.php app:tasks -c 4
  ```

The worker command is **in the same script**, so all you need is `multitron:worker` on your CLI to spawn worker processes.

---

## Notes

* **PSR-11 container** is only required for `$b->service(...)` or Nette/Symfony integration.
* **Closure tasks** work without any container wiring.
* The **worker** must be registered in the same Console application to handle IPC for your tasks.
