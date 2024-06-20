<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\Cancellation;
use Amp\Parallel\Context\ProcessContextFactory;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\Execution;
use Amp\Parallel\Worker\Internal\ContextWorker;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use SplObjectStorage;
use Throwable;

class WorkerPool
{
    /** @var Worker[] */
    private array $workers = [];

    private ProcessContextFactory $contextFactory;

    private SplObjectStorage $stderr;

    private SplObjectStorage $stdout;

    public function __construct()
    {
        $this->contextFactory = new ProcessContextFactory();
        $this->stderr = new SplObjectStorage();
        $this->stdout = new SplObjectStorage();
    }

    public function pull(): Worker
    {
        foreach ($this->workers as $worker) {
            if ($worker->isIdle()) {
                return $worker;
            }
        }
        $context = $this->contextFactory->start([ContextWorkerFactory::SCRIPT_PATH]);
        $cw = new ContextWorker($context);
        $this->stderr[$cw] = $context->getStderr();
        $this->stdout[$cw] = $context->getStdout();
        return $this->workers[] = $cw;
    }

    public function submit(Task $task, Cancellation $cancellation): Execution
    {
        $worker = $this->pull();
        $exec = $worker->submit($task, $cancellation);

        return new Execution($exec->getTask(), $exec->getChannel(), $exec->getFuture()->catch(function (Throwable $e) use ($worker) {

            throw new WorkerException($this->stderr[$worker], $this->stdout[$worker], $e);
        }));
    }

    public function shutdown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->shutdown();
        }
    }
}
