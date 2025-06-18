# Multitron: High-Performance PHP Task Orchestrator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/riki137/multitron.svg?style=flat-square)](https://packagist.org/packages/riki137/multitron)
[![Total Downloads](https://img.shields.io/packagist/dt/riki137/multitron.svg?style=flat-square)](https://packagist.org/packages/riki137/multitron)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/riki137/multitron/ci.yml?branch=main&style=flat-square)](https://github.com/riki137/multitron/actions?query=workflow%3Aci+branch%3Amain)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

Multitron is a **high-performance PHP task orchestrator** designed for fast parallel processing and CLI automation. Use it to run asynchronous or multi-process jobs with minimal effort. The library manages task dependencies, executes them concurrently and streams progress back to the console, supercharging any PHP workflow that relies on concurrency.

> **Note**: The project is still under active development and the API may change. It is however already used in production.

## Features

- Declarative task trees with dependency management for predictable workflows
- Parallel execution with automatic CPU detection and optimized concurrency
- Partitioned tasks for dividing large workloads across multiple cores
- Real-time progress output and console logging for full transparency
- Shared cache for inter-process communication and data sharing
- Seamless integration with Symfony Console applications
- MIT-licensed and fully open source

## Installation

Install the package via [Composer](https://getcomposer.org/) to start orchestrating tasks right away:

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

You can control how many tasks run at once via the `-c`/`--concurrency` option:

```bash
php bin/console app:tasks -c 8
```

The library will spawn up to eight worker processes and keep them busy until all tasks finish.

To limit which tasks run, pass a pattern as the first argument. Wildcards work the same as in `fnmatch()` and you may use `%` in place of `*` for convenience:

```bash
php bin/console app:tasks cache-* # run only tasks whose ID or tag matches "cache-*"
```

You can also tune how often progress updates are rendered using the `-u`/`--update-interval` option (in seconds):

```bash
php bin/console app:tasks -u 0.5
```

You can disable colors with `--no-colors` and switch off interactive table rendering using `--interactive=no`. The default `--interactive=detect` automatically falls back to plain output when run in CI.

### Central Cache

Within a task you receive a `TaskCommunicator` instance that provides simple methods to read and write data shared between tasks:

```php
use Multitron\Comms\TaskCommunicator;

final class MyTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $comm->cache->write(['foo' => ['bar' => 'baz']], 2);
        $baz = $comm->cache->read(['foo' => ['bar']])->await()['foo']['bar']; // baz
        $comm->cache->write(['stats' => ['hits' => ($values['stats']['hits'] ?? 0) + 1]], 2);
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
        $comm->progress->setTotal(100);
        for ($i = 0; $i < 100; $i++) {
            // ... work
            $comm->progress->addDone();
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

Help us shape the future of PHP concurrency by contributing your ideas and improvements.

## License

Multitron is released under the MIT License. See the [LICENSE](LICENSE) file for details.
