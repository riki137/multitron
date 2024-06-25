<?php
declare(strict_types=1);

namespace Multitron\Comms;

use Amp\Future;
use Amp\Sync\Channel;
use Multitron\Comms\Data\Message\LogLevel;
use Multitron\Comms\Data\Message\LogMessage;
use Multitron\Comms\Data\Message\Message;
use Multitron\Comms\Data\Message\TaskProgress;
use Multitron\Comms\Server\ChannelClient;
use Multitron\Comms\Server\ChannelResponse;
use Multitron\Comms\Server\OKResponse;
use Multitron\Comms\Server\Storage\CentralMergeKeyRequest;
use Multitron\Comms\Server\Storage\CentralReadKeyRequest;
use Multitron\Comms\Server\Storage\CentralReadResponse;
use Multitron\Comms\Server\Storage\CentralWriteKeyRequest;
use Multitron\Process\TaskThread;
use Multitron\Util\Throttle;

class TaskCommunicator
{
    private Throttle $throttle;

    private TaskProgress $progress;

    private ChannelClient $client;

    public function __construct(private readonly Channel $channel)
    {
        $this->progress = new TaskProgress(0);
        $this->throttle = new Throttle(function () {
            if (TaskThread::$inThread) {
                $this->progress->memoryUsage = memory_get_usage(true);
            }
            $this->sendMessage($this->progress);
        }, 50);
        $this->client = new ChannelClient($channel);
        $this->client->start();
    }

    public function read(string $key): ?array
    {
        $response = $this->client->send(new CentralReadKeyRequest($key));
        assert($response instanceof CentralReadResponse);
        return $response->data;
    }

    /**
     * @return Future<OKResponse>
     */
    public function write(string $key, array &$data): ChannelResponse
    {
        return $this->client->send(new CentralWriteKeyRequest($key, $data));
    }

    /**
     * @return Future<OKResponse>
     */
    public function merge(string $key, array $data): ChannelResponse
    {
        if ($data === []) {
            return new OKResponse();
        }
        return $this->client->send(new CentralMergeKeyRequest($key, $data));
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
    }
}
