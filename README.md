# Multitron

Multitron is a **high-performance PHP task orchestrator** for parallel processing and CLI automation. Use it to run asynchronous or multi-process jobs with minimal effort. The library manages task dependencies, executes them concurrently and streams progress back to the console.

> **Note**: The project is still under active development and the API may change. It is however already used in production.

## Features

- Declarative task trees with dependency management
- Parallel execution with automatic CPU detection
- Partitioned tasks for splitting large workloads
- Real-time progress output and console logging
- Shared cache for inter process communication

## Installation

Install the package via [Composer](https://getcomposer.org/):

```bash
composer require riki137/multitron
```

## Usage

Tasks implement the `Multitron\Execution\Task` interface. Here's a minimal example task:

```php
use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;

final class HelloTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $comm->log('Hello from a worker');
    }
}
```


You can register tasks in a command that extends `Multitron\Console\AbstractMultitronCommand`:

```php
use Multitron\Console\AbstractMultitronCommand;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeBuilderFactory;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'app:tasks')]
final class MyCommand extends AbstractMultitronCommand
{
    public function __construct(TaskTreeBuilderFactory $factory, TaskOrchestrator $orchestrator)
    {
        parent::__construct($factory, $orchestrator);
    }

    public function getNodes(TaskTreeBuilder $b): void
    {
        $cache = $b->group('cache-clear', [
            $b->service(ClearCacheTask::class),
            $b->service(ClearLogsTask::class),
        ]);

        $b->service(OtherCacheClearTask::class, [$cache]);
        $b->service(MyFirstTask::class);
        $second = $b->service(MySecondTask::class);
        $third = $b->service(MyThirdTask::class, [$second]);
        $b->partitioned(MyPartitionedTask::class, 4, [$third, $cache]);
    }
}
```

Register the command in your Symfony Console application and run it. Multitron will execute the tasks respecting dependencies and concurrency.

You can control how many tasks run at once via the `-c` option:

```bash
php bin/console app:tasks -c 8
```

The library will spawn up to eight worker processes and keep them busy until all tasks finish.

### Central Cache

Within a task you receive a `TaskCommunicator` instance that provides simple methods to read and write data shared between tasks:

```php
use Multitron\Comms\TaskCommunicator;

final class MyTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $data = $comm->read('key');
        $comm->merge('key', ['result' => 'done']);
        // advanced operations
        $cache = $comm->cache();
        $values = $cache->readKeys(['foo', 'stats' => ['hits']])->await();
        $cache->set(['foo', 'bar'], 'baz');
        $cache->merge('stats', ['hits' => ($values['stats']['hits'] ?? 0) + 1]);
    }
}
```

### Reporting Progress

Tasks can update progress counters that Multitron displays while running. Use
the `ProgressClient` provided by the communicator:

```php
final class DownloadTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $progress = $comm->progress();
        $progress->setTotal(100);
        for ($i = 0; $i < 100; $i++) {
            // ... work
            $progress->addDone();
        }
    }
}
```

You may also call `addOccurrence()` or `addWarning()` to report additional
metrics or warnings.

### Partitioned Tasks

When a workload can be split into chunks, partitioned tasks run those chunks in parallel. Define a task extending `PartitionedTask` and specify the number of partitions in the tree:

```php
use Multitron\Tree\Partition\PartitionedTask;

final class BuildReportTask extends PartitionedTask
{
    public function execute(TaskCommunicator $comm): void
    {
        $comm->log("processing part {$this->partitionIndex} of {$this->partitionCount}");
    }
}

$builder->partitioned(BuildReportTask::class, 4);
```

### Accessing CLI Options

Options passed on the command line are forwarded to each task. Retrieve them via
`TaskCommunicator`:

```php
final class ProcessUsersTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $limit = (int)($comm->getOption('limit') ?? 0);
        // ... process with the given $limit
    }
}
```

Call `getOptions()` to receive the entire array of options if needed.


### Custom Progress Output

Multitron renders progress using a `ProgressOutputFactory`. Replace the default table display or combine outputs with `ChainProgressOutputFactory`:

```php
use Multitron\Orchestrator\Output\ChainProgressOutputFactory;
use Multitron\Orchestrator\Output\TableOutputFactory;

$factory = new ChainProgressOutputFactory(
    new TableOutputFactory(),
    new JsonOutputFactory(), // your custom factory
);
$orchestrator = new TaskOrchestrator($ipc, $container, $execFactory, $factory, $handlerFactory);
```

Implement the factory to send progress anywhere you like.

## Contributing


Issues and pull requests are welcome. Feel free to open a discussion on GitHub.

## License

Multitron is released under the MIT License. See the [LICENSE](LICENSE) file for details.
