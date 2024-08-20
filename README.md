# Multitron

Multitron is a PHP-based task orchestration and execution library designed to efficiently run and manage complex task trees. 
It's designed to handle parallel task execution, inter-process communication, and resource management.

> ⚠️ This library is still in early development and may have breaking changes in the future.
> However, it is already being used in production and actively developed.

## Features

- Task orchestration and dependency management
- Concurrent execution with configurable task concurrency
- Real-time progress updates and logging
- Support for task partitioning and advanced routing
- Shared memory and inter-process communication via channels and semaphores
- Error handling and logging using PSR-3 and Tracy

## Installation

Multitron can be installed using [Composer](https://getcomposer.org/):

```sh
composer require riki137/multitron
```

## Usage

### Basic Task Tree

Define your custom tasks by implementing the `Task` interface or extending the `SimpleTask` abstract class. Inject these tasks into a task tree using a dependency injection container.

```php
use Multitron\Container\TaskTree;
use Psr\Container\ContainerInterface;

class MyTaskTree extends TaskTree
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    public function build(): Generator
    {
        yield $cacheClear = $this->group('cache-clear', function() {
            yield $this->task(ClearCacheTask::class);
            yield $this->task(ClearLogsTask::class);
        });
        yield $this->task(OtherCacheClearTask::class)->belongsTo($cacheClear);
        
        yield $this->task(MyFirstTask::class);
        yield $secondTask = $this->task(MySecondTask::class);
        yield $thirdTask = $this->task(MyThirdTask::class)->dependsOn($secondTask);
        yield $this->partitioned(MyPartitionedTask::class, 4)->dependsOn($thirdTask, $cacheClear);
        
    }
}
```

### Running the Task Tree

To run the task tree, instantiate the `Multitron` command class with the appropriate arguments:

```php
use Multitron\Multitron;
use Multitron\Error\TracyErrorHandler;
use Symfony\Component\Console\Application;

$taskTree = new MyTaskTree($container);
$bootstrapPath = '/path/to/bootstrap.php'; // Path to the bootstrap file that returns an instance of a PSR container or Nette Container
$concurrentTasks = 4; // Number of concurrent tasks
$errorHandler = new TracyErrorHandler(); // see below for PSR logger

$multitron = new Multitron($taskTree, $bootstrapPath, $concurrentTasks, $errorHandler);

$application = new Application();
$application->add($multitron);
$application->run();
```

### Error Logging

Configure error handling by using either a PSR-3 logger or Tracy for detailed error reports.

#### PSR-3 Logger

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Multitron\Error\PsrLogErrorHandler;

$logger = new Logger('multitron');
$logger->pushHandler(new StreamHandler('path/to/logfile.log', Logger::ERROR));

$errorHandler = new PsrLogErrorHandler($logger);
```

#### Tracy

```php
use Multitron\Error\TracyErrorHandler;
$errorHandler = new TracyErrorHandler();
```

### Progress Reporting and Logging

Multitron provides real-time logging and progress updates that can be configured using the provided classes.

```php
use Multitron\Output\TableOutput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

$tableOutput = new TableOutput();
$tableOutput->configure($inputConfiguration);
```

### Task Partitioning

Partition tasks to run chunks of tasks in parallel, each of which operates on a subset of data.

```php
use Multitron\Container\Node\PartitionedTaskGroupNode;

$partitionedNode = new PartitionedTaskGroupNode("MyPartitionedTask", function() use ($container) {
    return $container->get(MyPartitionedTask::class);
}, 4); // Partition into 4 chunks
```

### Central Cache with TaskCommunicator

The Central Cache in Multitron offers a global shared memory space for tasks to read and write data efficiently across different task instances. This functionality is facilitated through the `TaskCommunicator`, which serves as an intermediary for these operations within the `execute` method of a task.

#### Operations Provided by TaskCommunicator

Within the context of a task's execution, the `TaskCommunicator` provides four key methods that interact with the Central Cache:

1. **`$comm->read(string $key): ?array`**

   This method allows a task to read data from the Central Cache associated with the specified key. If the key exists, it retrieves the corresponding data and returns it as an array. If the key does not exist, it returns `null`.

   **Example Usage:**

   ```php
   $data = $comm->read('task_result');
   if ($data !== null) {
       // Process the retrieved data
   } else {
       // Handle the absence of data
   }
   ```

2. **`$comm->readSubset(string $key, array $subkeys): ?array`**

   This method reads a subset of the data stored under a specified key. It takes a key and an array of subkeys. It retrieves data for each of the provided subkeys if they exist, returning an associative array containing the subkeys and their corresponding values. If any subkey is not found, it will simply omit them from the result.

   **Example Usage:**

   ```php
   $subData = $comm->readSubset('user_emails', [123,126]); // Retrieve emails for user IDs 123 and 126
   // Note: Similarly to array_intersect_key(), only the existing keys are returned
   $email = $subData[123] ?? null;
   ```

3. **`$comm->write(string $key, array &$data): Future`**

   This method allows tasks to write or update data in the Central Cache associated with a specified key. It takes the key and a reference to the data array to be stored. The method asynchronously writes the data and returns a `Future`, allowing the task to continue execution without blocking.

   **Example Usage:**

   ```php
   $dataToStore = [123 => 'richard@popelis.sk', 124 => 'fero@example.org'];
   $comm->write('user_emails', $dataToStore)->await();
   ```

4. **`$comm->merge(string $key, array $data, int $level = 1): Future`**

   The `merge` method is used to merge new data into the existing data structure under a specified key in the Central Cache. It takes a key, the data to merge, and an optional merge level indicating how deep the array structure should be merged. This is particularly useful for hierarchical or nested data structures.

   **Example Usage:**

   ```php
   $newResults = ['subtask1' => ['status' => 'done'], 'subtask2' => ['status' => 'pending']];
   $comm->merge('project_results', $newResults, 1)->await();
   // The project_results key will have its data updated without overwriting existing entries
   ```

### Summary of Use

The `TaskCommunicator` thus serves as a powerful tool for managing shared state through the Central Cache. By leveraging the above methods, tasks can effectively record and retrieve data needed for their execution, enhancing coordination and state management across a potentially complex and parallelized workflow.

Each of these methods is designed to facilitate easy and efficient data-sharing during the task execution phase, allowing developers to build scalable and responsive systems that make full use of available resources while maintaining the integrity of shared data.

## Contributing

Contributions, issues, and feature requests are welcome!

Feel free to check [issues page](https://github.com/username/multitron/issues) if you want to contribute.

## License

Multitron is licensed under the MIT License. See the LICENSE file for more details.
