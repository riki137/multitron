<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\Parallel\Context\ProcessContextFactory;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\Execution;
use Amp\Parallel\Worker\Internal\ContextWorker;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use SplObjectStorage;
use Throwable;
use function Amp\async;

class WorkerFactory
{
    private array $workers = [];

    private ProcessContextFactory $contextFactory;

    private SplObjectStorage $stderr;

    private SplObjectStorage $stdout;

    public function __construct(private readonly string $bootstrapPath, int $workerBuffer = 8)
    {
        $this->contextFactory = new ProcessContextFactory();
        $this->stderr = new SplObjectStorage();
        $this->stdout = new SplObjectStorage();

        for ($i = 0; $i < $workerBuffer; $i++) {
            async($this->bufferWorker(...));
        }
    }

    private function bufferWorker(): void
    {
        $this->workers[] = $this->createWorker();
    }

    private function createWorker(): Worker
    {
        $start = microtime(true);
        $context = $this->contextFactory->start([ContextWorkerFactory::SCRIPT_PATH]);
        $cw = new ContextWorker($context);
        $cw->submit(new TaskThread(TaskThread::LOAD_CONTAINER, $this->bootstrapPath))->await();
        $this->stderr[$cw] = $context->getStderr();
        $this->stdout[$cw] = $context->getStdout();
        register_shutdown_function(function () use ($cw) {
            try {
                $cw->shutdown();
            } catch (Throwable) {
            }
        });
        return $cw;
    }

    private function pull(): Worker
    {
        async($this->bufferWorker(...));
        return array_pop($this->workers) ?? $this->createWorker();
    }

    public function submit(Task $task): Execution
    {
        $worker = $this->pull();
        $exec = $worker->submit($task);

        return new Execution($exec->getTask(), $exec->getChannel(), $exec->getFuture()->catch(function (Throwable $e) use ($worker) {
            throw new WorkerException($this->stderr[$worker], $this->stdout[$worker], $e);
        })->finally(function () use ($worker) {
            $worker->shutdown();
            $this->stderr->detach($worker);
            $this->stdout->detach($worker);
        }));
    }
}
