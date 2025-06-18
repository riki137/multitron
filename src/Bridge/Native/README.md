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

1. **Bootstrap**: load Composer’s autoloader.
2. **Wire core services**: IPC peer, execution factory, handler factory, output factory.
3. **Instantiate** a PSR-11 container (stub if you only use closures).
4. **Build** `TaskTreeBuilderFactory` and `TaskOrchestrator`.
5. **Register** two commands on the same `Symfony\Component\Console\Application`:

    * `Multitron\Console\MultitronWorkerCommand` (runnable via `multitron:worker`)
    * Your custom task command (e.g. `app:tasks`) extending `AbstractMultitronCommand`
6. **Run** the application.

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Psr\Container\ContainerInterface;
use Multitron\Tree\TaskTreeBuilderFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Execution\ProcessExecutionFactory;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Execution\Handler\DefaultIpcHandlerRegistryFactory;
use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use Multitron\Execution\Handler\ProgressServer;
use StreamIpc\NativeIpcPeer;
use Symfony\Component\Console\Application;
use Multitron\Console\MultitronWorkerCommand;
use Multitron\Console\AbstractMultitronCommand;

// 1. IPC & execution setup
$ipc         = new NativeIpcPeer();
$execFactory = new ProcessExecutionFactory($ipc);
$handlerFact = new DefaultIpcHandlerRegistryFactory(
    new MasterCacheServer(),
    new ProgressServer()
);
$outputFact  = new TableOutputFactory();

// 2. PSR-11 container stub
$container = new class implements ContainerInterface {
    public function get(string $id)       { throw new \RuntimeException("Service {$id} not found"); }
    public function has(string $id): bool { return false; }
};

// 3. Builder & orchestrator
$builderFact  = new TaskTreeBuilderFactory($container);
$orchestrator = new TaskOrchestrator(
    $ipc,
    $execFactory,
    $outputFact,
    $handlerFact
);

// 4. Console application
$app = new Application('My Multitron App', '1.0');

// 5a. Worker command (must be registered here)
$app->add(new MultitronWorkerCommand($ipc));

// 5b. Your task command
$app->add(new class($builderFact, $orchestrator) extends AbstractMultitronCommand {
    protected static $defaultName = 'app:tasks';

    public function getNodes(\Multitron\Tree\TaskTreeBuilder $b): array
    {
        return [
            // Example closure-based task:
            $b->task('say-hello', fn() => new class implements \Multitron\Execution\Task {
                public function execute(\Multitron\Comms\TaskCommunicator $comm): void
                {
                    $comm->log('👋 Hello from Multitron!');
                }
            }),
            // Add more tasks, use $b->service(), $b->group(), $b->partitioned(), etc.
        ];
    }
});

$app->run();
```

Make it executable:

```bash
chmod +x bin/multitron.php
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
