<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\ByteStream\ReadableResourceStream;
use Amp\Future;
use Amp\Parallel\Context\ProcessContextFactory;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\Execution;
use Amp\Parallel\Worker\Internal\ContextWorker;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Multitron\Comms\Data\Message\Message;
use Multitron\Comms\Server\ChannelRequest;
use SplObjectStorage;
use Throwable;
use function Amp\async;

class WorkerFactory
{
    /**
     * @var Future<Worker>[]
     */
    private array $workers = [];

    private ProcessContextFactory $contextFactory;

    /**
     * @var SplObjectStorage<Worker, ReadableResourceStream>
     */
    private SplObjectStorage $stderr;

    /**
     * @var SplObjectStorage<Worker, ReadableResourceStream>
     */
    private SplObjectStorage $stdout;

    public function __construct(private readonly string $bootstrapPath, int $workerBuffer, private int $softLimit)
    {
        $this->contextFactory = new ProcessContextFactory();
        $this->stderr = new SplObjectStorage();
        $this->stdout = new SplObjectStorage();

        for ($i = 0; $i < $workerBuffer; $i++) {
            $this->bufferWorker();
        }
    }

    private function bufferWorker(): void
    {
        if (--$this->softLimit > 0) {
            $this->workers[] = async($this->createWorker(...));
        }
    }

    private function createWorker(): Worker
    {
        $context = $this->contextFactory->start([ContextWorkerFactory::SCRIPT_PATH]);
        $cw = new ContextWorker($context);
        $cw->submit(new TaskThread(TaskThread::LOAD_CONTAINER, [], $this->bootstrapPath))->await();
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
        $this->bufferWorker();
        $worker = array_shift($this->workers);
        if ($worker instanceof Future) {
            return $worker->await();
        }
        $this->softLimit--;
        return $this->createWorker();
    }

    /**
     * @param Task<int, Message, ChannelRequest> $task
     * @return Execution<int, Message, ChannelRequest>
     */
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
