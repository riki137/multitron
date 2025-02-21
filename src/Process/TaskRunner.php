<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\Future;
use Amp\Pipeline\Queue;
use Multitron\Comms\Server\ChannelServer;
use Multitron\Comms\Server\Semaphore\SemaphoreHandler;
use Multitron\Comms\Server\Storage\CentralCacheHandler;
use Multitron\Console\MultitronConfig;
use Multitron\Container\Node\TaskNode;
use Multitron\Container\Node\TaskNodeLeaf;
use Multitron\Container\Node\TaskTreeProcessor;
use Throwable;
use function Amp\async;
use function Amp\Future\awaitAll;

class TaskRunner
{
    public const NON_BLOCKING = '__NB__';

    private WorkerFactory $workerFactory;

    private ChannelServer $server;

    private Queue $processes;

    private int $concurrentTasks;

    private TaskTreeProcessor $tree;

    public function __construct(
        TaskNode $rootNode,
        private readonly MultitronConfig $config,
        private readonly array $options
    ) {
        $this->tree = new TaskTreeProcessor($rootNode);
        $this->concurrentTasks = (int)($options['concurrency'] ?? (int)shell_exec('nproc'));
        $this->workerFactory = new WorkerFactory($config->getBootstrapPath(), min((int)($this->concurrentTasks / 2), 6), count($this->tree->getLeaves()));
        $this->server = new ChannelServer([new CentralCacheHandler(), new SemaphoreHandler()]);
        $this->processes = new Queue();
    }

    /**
     * @return iterable<TaskNodeLeaf>
     */
    public function getLeaves(): iterable
    {
        return $this->tree->getLeaves();
    }

    public function runAll(): Future
    {
        return async(function () {
            $failed = [];
            $exitCode = 0;
            $queue = new TaskQueue($this->concurrentTasks, $this->tree, $this->config->getErrorHandler());
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
                    } catch (Throwable) {
                        $exitCode = 1;
                        $failed[$taskId] = true;
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

    private function runTask(TaskNodeLeaf $task, ChannelServer $server, array &$failed): RunningTask
    {
        foreach ($this->tree->getDependentIds($task) as $dependency) {
            if (isset($failed[$dependency])) {
                return new SkippedTask($server);
            }
        }

        if ($task->hasGroup(self::NON_BLOCKING)) {
            $local = new LocalTask($task, $server, $this->config->getErrorHandler());
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
                $this->config->getErrorHandler()->internalError($e);
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
