<?php
declare(strict_types=1);

namespace Multitron\Comms;

use Amp\Cancellation;
use Amp\Sync\Channel;
use ArrayObject;
use Multitron\Comms\Data\Message\LogLevel;
use Multitron\Comms\Data\Message\LogMessage;
use Multitron\Comms\Data\Message\Message;
use Multitron\Comms\Data\Message\TaskProgress;
use Multitron\Process\SharedMemory;
use Multitron\Process\TaskThread;
use Multitron\Util\Throttle;

class TaskCommunicator
{
    private Throttle $throttle;

    private TaskProgress $progress;

    public function __construct(
        private readonly SharedMemory $sharedMemory,
        private readonly Channel $channel,
        private readonly Cancellation $cancellation
    ) {
        $this->progress = new TaskProgress(0);
        $this->throttle = new Throttle(function () {
            if (TaskThread::$inThread) {
                $this->progress->memoryUsage = memory_get_usage(true);
            }
            $this->sendMessage($this->progress);
        }, 50);
    }

    public function read(string $key): ArrayObject
    {
        return $this->sharedMemory->get($key);
    }

    public function update(string $key, callable $updater): void
    {
        $this->sharedMemory->update($key, $updater);
    }

    public function sendMessage(Message $data): void
    {
        $this->channel->send($data);
    }

    public function log(string $message, LogLevel $level = LogLevel::INFO): void
    {
        $this->sendMessage(new LogMessage($message, $level));
    }

    public function error(string $message): void
    {
        $this->log($message, LogLevel::ERROR);
    }

    public function getProgress(): TaskProgress
    {
        return $this->progress;
    }

    public function sendProgress(bool $force = false): void
    {
        $this->throttle->call($force);
    }

    public function onCancel(callable $callback): void
    {
        $this->cancellation->subscribe($callback);
    }

    public function setTotal(int $total): void
    {
        $this->progress->total = $total;
        $this->sendProgress(true);
    }

    public function addDone(int $done = 1): void
    {
        $this->progress->done += $done;
        $this->sendProgress();
    }

    public function addError(int $error = 1): void
    {
        $this->progress->error += $error;
        $this->sendProgress();
    }

    public function addWarning(int $warning = 1): void
    {
        $this->progress->warning += $warning;
        $this->sendProgress();
    }

    public function addSkipped(int $skipped = 1): void
    {
        $this->progress->skipped += $skipped;
        $this->sendProgress();
    }

    public function shutdown(): void
    {
        $this->throttle->shutdown();
        $this->channel->close();
    }
}
