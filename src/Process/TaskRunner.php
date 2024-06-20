<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\DeferredCancellation;
use Amp\Pipeline\Queue;
use Multitron\Container\Node\TaskLeafNode;
use Multitron\Container\Node\TaskTreeProcessor;
use Throwable;
use Tracy\Debugger;
use function Amp\async;

class TaskRunner
{
    private WorkerPool $workerPool;

    private SharedMemory $sharedMemory;

    private Queue $processes;

    public function __construct(private readonly TaskTreeProcessor $tree, private readonly int $concurrentTasks, private readonly ?string $bootstrapPath = null)
    {
        $this->workerPool = new WorkerPool();
        $this->sharedMemory = new SharedMemory(null, null);
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
        $queue = new TaskQueue($this->concurrentTasks, $this->tree);
        $all = [];
        foreach ($queue->fetchAll() as $taskId => $taskNode) {
            $runningTask = $this->runTask($taskNode);
            $this->processes->pushAsync([$taskId, $runningTask]);
            $all[] = async(function () use ($taskId, $queue, $runningTask) {
                try {
                    $runningTask->getFuture()->await();
                } finally {
                    $queue->markFinished($taskId);
                }
            });
        }
        foreach ($all as $runningTask) {
            $runningTask->await();
        }
        $this->processes->complete();
    }

    private function runTask(TaskLeafNode $task): RunningTask
    {
        if ($task->isNonBlocking()) {
            $local = new LocalTask($this->sharedMemory, $task->getTask());
            $local->run();
            return $local;
        }

        $cancel = new DeferredCancellation();
        while (true) {
            try {
                return new WorkerTask(
                    $this->workerPool->submit(
                        new TaskThread($this->sharedMemory->semaphoreKey, $this->sharedMemory->parcelKey, $this->bootstrapPath, $task->getId()),
                        $cancel->getCancellation()
                    ),
                    $cancel
                );
            } catch (Throwable $e) {
                Debugger::log($e->getMessage(), 'worker-crash');
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

    public function shutdown(): void
    {
        $this->workerPool->shutdown();
    }
}
