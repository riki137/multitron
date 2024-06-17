<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Closure;
use Multitron\Comms\Data\Message\Message;
use Multitron\Comms\LocalChannel;
use Multitron\Comms\TaskCommunicator;
use Multitron\Impl\Task;
use Throwable;
use function Amp\async;

class LocalTask implements RunningTask
{
    private LocalChannel $channel;

    private DeferredCancellation $cancel;

    private DeferredFuture $future;

    private TaskCentre $centre;

    public function __construct(private readonly SharedMemory $sharedMemory, private readonly Task $task)
    {
        $this->channel = new LocalChannel();
        $this->cancel = new DeferredCancellation();
        $this->future = new DeferredFuture();
        $this->centre = new TaskCentre($this->channel, $this->cancel->getCancellation());
    }

    public function run(): void
    {
        $communicator = new TaskCommunicator($this->sharedMemory, $this->channel, $this->cancel->getCancellation());
        try {
            $exec = async(fn() => $this->task->execute($communicator));
            async(function () use ($communicator, $exec) {
                try {
                    $this->future->complete($exec->await($this->cancel->getCancellation()));
                } catch (Throwable $e) {
                    $communicator->log($e->getMessage());
                    $this->future->error($e);
                } finally {
                    $this->channel->close();
                }
            });
        } catch (Throwable $e) {
            $communicator->log($e->getMessage());
        }
    }

    public function await(): mixed
    {
        return $this->future->getFuture()->await($this->cancel->getCancellation());
    }

    public function finally(Closure $closure): void
    {
        $this->future->getFuture()->finally($closure);
    }

    public function receive(): Message
    {
        return $this->channel->receive($this->cancel->getCancellation());
    }

    public function cancel(): void
    {
        $this->cancel->cancel();
    }

    public function getCentre(): TaskCentre
    {
        return $this->centre;
    }
}
