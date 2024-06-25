<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Multitron\Comms\Data\Message\Message;
use Multitron\Comms\Data\Message\SuccessMessage;
use Multitron\Comms\Local\LocalChannel;
use Multitron\Comms\Local\LocalChannelPair;
use Multitron\Comms\Server\ChannelServer;
use Multitron\Comms\TaskCommunicator;
use Multitron\Impl\Task;
use Throwable;
use Tracy\Debugger;
use function Amp\async;

class LocalTask implements RunningTask
{

    private LocalChannelPair $channels;

    private DeferredCancellation $cancel;

    private DeferredFuture $future;
    private TaskCentre $centre;

    public function __construct(private readonly Task $task, ChannelServer $server)
    {
        $this->channels = new LocalChannelPair();
        $this->cancel = new DeferredCancellation();
        $this->future = new DeferredFuture();
        $this->centre = new TaskCentre($this->channels->parent, $server, $this->cancel->getCancellation());
    }

    public function run(): void
    {
        $communicator = new TaskCommunicator($this->channels->child);
        $exec = async(fn() => $this->task->execute($communicator));
        $catcher = async(function () use ($communicator, $exec) {
            try {
                $this->future->complete($exec->await($this->cancel->getCancellation()));
                $communicator->sendProgress(true);
                $communicator->sendMessage(new SuccessMessage());
            } catch (Throwable $e) {
                $communicator->log($e->getMessage());
                Debugger::log($e);
                $this->future->error($e);
            }
            $communicator->shutdown();
        });
    }

    public function getFuture(): Future
    {
        return $this->future->getFuture();
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
