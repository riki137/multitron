<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\DeferredCancellation;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Parallel\Worker\WorkerException;
use Amp\Parallel\Worker\WorkerPool;
use Amp\Pipeline\Queue;
use Multitron\Container\Node\TaskLeafNode;
use Multitron\Container\Node\TaskTreeProcessor;
use Tracy\Debugger;
use function Amp\delay;

class TaskRunner
{
    private ContextWorkerPool $workerPool;

    private SharedMemory $sharedMemory;

    private Queue $processes;

    public function __construct(private readonly TaskTreeProcessor $tree, ?int $concurrentTasks, private readonly ?string $bootstrapPath = null)
    {
        $this->workerPool = new ContextWorkerPool($concurrentTasks, new ContextWorkerFactory($bootstrapPath));
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
        $queue = new TaskQueue($this->workerPool->getLimit(), $this->tree);
        $all = [];
        foreach ($queue->fetchAll() as $taskId => $taskNode) {
            $all[] = $runningTask = $this->runTask($taskNode);
            $this->processes->pushAsync([$taskId, $runningTask]);
            $runningTask->finally(fn() => $queue->markFinished($taskId));
        }
        foreach ($all as $runningTask) {
            $runningTask->await();
        }
        $this->processes->complete();
    }

    private function runTask(TaskLeafNode $task): RunningTask
    {
        if ($task->isAsync()) {
            $local = new LocalTask($this->sharedMemory, $task->getTask());
            $local->run();
            return $local;
        }

        $cancel = new DeferredCancellation();
        while (true) {
            try {
                return new IsolatedTask(
                    $this->workerPool->submit(
                        new TaskThread($this->sharedMemory->semaphoreKey, $this->sharedMemory->parcelKey, $this->bootstrapPath, $task->getId()),
                        $cancel->getCancellation()
                    ),
                    $cancel
                );
            } catch (WorkerException $e) {
                Debugger::log($e->getMessage(), 'worker-crash');
                delay(1);
            }
        }
    }

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
