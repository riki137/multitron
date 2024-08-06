<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\DeferredFuture;
use Amp\Future;
use Multitron\Comms\Data\Message\SuccessMessage;
use Multitron\Comms\Local\LocalChannelPair;
use Multitron\Comms\Server\ChannelServer;
use Multitron\Comms\TaskCommunicator;
use Multitron\Container\Node\TaskLeafNode;
use Multitron\Error\ErrorHandler;
use Multitron\Impl\Task;
use Throwable;
use function Amp\async;

class LocalTask implements RunningTask
{
    private LocalChannelPair $channels;

    private DeferredFuture $future;

    private TaskCentre $centre;

    private string $taskId;

    private Task $task;

    public function __construct(TaskLeafNode $taskNode, ChannelServer $server, private readonly ErrorHandler $errorHandler)
    {
        $this->channels = new LocalChannelPair();
        $this->future = new DeferredFuture();
        $this->centre = new TaskCentre($this->channels->parent, $server);
        $this->taskId = $taskNode->getId();
        $this->task = $taskNode->getTask();
    }

    public function run(array $options): void
    {
        $communicator = new TaskCommunicator($this->channels->child, $options);
        $catcher = async(function () use ($communicator) {
            try {
                $this->task->execute($communicator);
                $this->future->complete(0);
                $communicator->sendProgress(true);
                $communicator->sendMessage(new SuccessMessage());
            } catch (Throwable $e) {
                $communicator->error($this->errorHandler->taskError($this->taskId, $e));
                $this->future->error($e);
            }
            $communicator->shutdown();
        });
    }

    public function getFuture(): Future
    {
        return $this->future->getFuture();
    }

    public function getCentre(): TaskCentre
    {
        return $this->centre;
    }
}
