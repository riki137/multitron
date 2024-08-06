<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\Future;
use Amp\Pipeline\Queue;
use Multitron\Comms\Server\ChannelServer;
use Multitron\Comms\Server\Semaphore\SemaphoreHandler;
use Multitron\Comms\Server\Storage\CentralCache;
use Multitron\Container\Node\TaskLeafNode;
use Multitron\Container\Node\TaskTreeProcessor;
use Multitron\Error\ErrorHandler;
use Throwable;
use function Amp\async;
use function Amp\Future\awaitAll;

class TaskRunner
{
    private WorkerFactory $workerFactory;

    private ChannelServer $server;

    private Queue $processes;

    public function __construct(
        private readonly TaskTreeProcessor $tree,
        private readonly int $concurrentTasks,
        string $bootstrapPath,
        private readonly ErrorHandler $errorHandler,
        private readonly array $options
    ) {
        $this->workerFactory = new WorkerFactory($bootstrapPath, min((int)($this->concurrentTasks / 2), 6), count($tree->getNodes()));
        $this->server = new ChannelServer([new CentralCache(), new SemaphoreHandler()]);
        $this->processes = new Queue();
    }

    /**
     * @return iterable<TaskLeafNode>
     */
    public function getNodes(): iterable
    {
        return $this->tree->getNodes();
    }

    public function runAll(): Future
    {
        return async(function () {
            $failed = [];
            $exitCode = 0;
            $queue = new TaskQueue($this->concurrentTasks, $this->tree, $this->errorHandler);
            $all = [];
            foreach ($queue->fetchAll() as $taskId => $taskNode) {
                $runningTask = $this->runTask($taskNode, $this->server, $failed);
                $this->processes->pushAsync([$taskId, $runningTask]);
                $all[] = async(function () use ($taskId, $queue, $runningTask, &$exitCode, &$failed) {
                    try {
                        $taskExitCode = $runningTask->getFuture()->await();
                        if (is_int($taskExitCode) && $taskExitCode > 0) {
                            $exitCode = 1;
                            $failed[$taskId] = true;
                        }
                    } finally {
                        $queue->markFinished($taskId);
                    }
                });
            }
            awaitAll($all);
            $this->processes->complete();
            return $exitCode;
        });
    }

    private function runTask(TaskLeafNode $task, ChannelServer $server, array &$failed): RunningTask
    {
        foreach ($this->tree->getDependencies($task) as $dependency) {
            if (isset($failed[$dependency])) {
                return new SkippedTask($server);
            }
        }

        if ($task->isNonBlocking()) {
            $local = new LocalTask($task, $server, $this->errorHandler);
            $local->run($this->options);
            return $local;
        }

        while (true) {
            try {
                return new WorkerTask(
                    $this->workerFactory->submit(
                        new TaskThread($task->getId(), $this->options),
                    ),
                    $server
                );
            } catch (Throwable $e) {
                $this->errorHandler->internalError($e);
            }
        }
    }

    /**
     * @return iterable<string, RunningTask>
     */
    public function getProcesses(): iterable
    {
        foreach ($this->processes->iterate() as [$taskId, $runningTask]) {
            yield $taskId => $runningTask;
        }
    }
}
