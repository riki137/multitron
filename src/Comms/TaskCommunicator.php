<?php
declare(strict_types=1);

namespace Multitron\Comms;

use Amp\Cancellation;
use Amp\Sync\Channel;
use ArrayObject;
use Multitron\Comms\Data\Message\LogLevel;
use Multitron\Comms\Data\Message\LogMessage;
use Multitron\Comms\Data\Message\Message;
use Multitron\Comms\Data\Message\ProgressMessage;
use Multitron\Process\SharedMemory;
use Multitron\Util\Throttle;

class TaskCommunicator
{
    private Throttle $throttle;

    private ProgressMessage $lastProgress;

    public function __construct(
        private readonly SharedMemory $sharedMemory,
        private readonly Channel $channel,
        private readonly Cancellation $cancellation
    ) {
        $this->lastProgress = new ProgressMessage(0);
        $this->throttle = new Throttle(fn() => $this->sendMessage($this->lastProgress), 50);
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

    public function sendProgress(int $total, int $done, int $error = 0, int $warning = 0, int $skipped = 0): void
    {
        $this->lastProgress->total = $total;
        $this->lastProgress->done = $done;
        $this->lastProgress->error = $error;
        $this->lastProgress->warning = $warning;
        $this->lastProgress->skipped = $skipped;
        $this->throttle->call(true);
    }

    public function onCancel(callable $callback): void
    {
        $this->cancellation->subscribe($callback);
    }
}
