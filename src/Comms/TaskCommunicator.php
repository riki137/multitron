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
use Multitron\Comms\Server\ChannelRequest;
use Multitron\Comms\Server\OKResponse;
use Multitron\Comms\Server\Storage\CentralReadKeyRequest;
use Multitron\Comms\Server\Storage\CentralReadResponse;
use Multitron\Comms\Server\Storage\CentralReadSubsetRequest;
use Multitron\Comms\Server\Storage\CentralReadSubsetsRequest;
use Multitron\Comms\Server\Storage\CentralWriteKeyRequest;
use Multitron\Output\Table\CentralMergeKeyRequest;
use Multitron\Process\MemoryUsage;
use Multitron\Process\TaskThread;
use Multitron\Util\Throttle;

class TaskCommunicator
{
    private Throttle $throttle;

    private TaskProgress $progress;

    private ChannelClient $client;

    private ?MemoryUsage $memoryUsage = null;

    public function __construct(private readonly Channel $channel, private readonly array $options)
    {
        if (TaskThread::$inThread) {
            $this->memoryUsage = new MemoryUsage();
        }
        $this->progress = new TaskProgress(0);
        $this->throttle = new Throttle(function () {
            $this->progress->memoryUsage = $this->memoryUsage?->getMemoryUsage();
            $this->sendMessage($this->progress);
        }, 50);
        $this->client = new ChannelClient($channel);
        $this->client->start();
    }

    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function &read(string $key): ?array
    {
        $response = $this->client->send(new CentralReadKeyRequest($key))->await();
        assert($response instanceof CentralReadResponse);
        return $response->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function &readSubset(string $key, array $subkeys): array
    {
        /** @var CentralReadResponse<array> $response */
        $response = $this->client->send(new CentralReadSubsetRequest($key, $subkeys))->await();
        return $response->data;
    }

    /**
     * @param array<string, string[]> $subsets
     * @return array<string, array<string, mixed>>
     */
    public function readSubsets(array $subsets): array
    {
        /** @var CentralReadResponse<array<string, array<string, mixed>>> $response */
        $response = $this->client->send(new CentralReadSubsetsRequest($subsets))->await();
        return $response->data;
    }

    /**
     * @return Future<OKResponse> future is returned after the request is sent
     */
    public function write(string $key, array &$data): Future
    {
        return $this->client->send(new CentralWriteKeyRequest($key, $data));
    }

    /**
     * @param int<1,max> $level the n-dimensional level to merge the data
     * @return Future<OKResponse> future is returned after the request is sent
     */
    public function merge(string $key, array $data, int $level = 1): Future
    {
        if ($data === []) {
            return Future::complete(new OKResponse());
        }
        return $this->client->send(new CentralMergeKeyRequest($key, $data, $level));
    }

    public function sendRequest(ChannelRequest $request): Future
    {
        return $this->client->send($request);
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
        $this->client->shutdown();
    }
}
