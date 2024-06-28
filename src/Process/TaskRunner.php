<?php
declare(strict_types=1);

namespace Multitron\Process;

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

    public function __construct(private readonly TaskTreeProcessor $tree, private readonly int $concurrentTasks, string $bootstrapPath, private readonly ErrorHandler $errorHandler)
    {
        $this->workerFactory = new WorkerFactory($bootstrapPath);
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

    public function runAll(): void
    {
        $queue = new TaskQueue($this->concurrentTasks, $this->tree, $this->errorHandler);
        $all = [];
        foreach ($queue->fetchAll() as $taskId => $taskNode) {
            $runningTask = $this->runTask($taskNode, $this->server);
            $this->processes->pushAsync([$taskId, $runningTask]);
            $all[] = async(function () use ($taskId, $queue, $runningTask) {
                try {
                    $runningTask->getFuture()->await();
                } finally {
                    $queue->markFinished($taskId);
                }
            });
        }
        awaitAll($all);
        $this->processes->complete();
    }

    private function runTask(TaskLeafNode $task, ChannelServer $server): RunningTask
    {
        if ($task->isNonBlocking()) {
            $local = new LocalTask($task, $server, $this->errorHandler);
            $local->run();
            return $local;
        }

        while (true) {
            try {
                return new WorkerTask(
                    $this->workerFactory->submit(
                        new TaskThread($task->getId()),
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
