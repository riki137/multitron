<?php

declare(strict_types=1);

namespace Multitron\Comms;

use PhpStreamIpc\Envelope\ResponsePromise;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;

final readonly class TaskCommunicator
{
    private MasterCacheClient $cache;

    private ProgressClient $progress;

    public function __construct(
        private IpcSession $session,
        /** @var array<string, mixed> $options */
        private array $options,
        ?MasterCacheClient $cache = null,
        ?ProgressClient $progress = null
    ) {
        $this->cache = $cache ?? new MasterCacheClient($session);
        $this->progress = $progress ?? new ProgressClient($session);
    }

    public function notify(Message $message): void
    {
        $this->session->notify($message);
    }

    public function request(Message $message): ResponsePromise
    {
        return $this->session->request($message);
    }

    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function cache(): MasterCacheClient
    {
        return $this->cache;
    }

    public function progress(): ProgressClient
    {
        return $this->progress;
    }

    public function log(string $message, string $level = 'info'): void
    {
        $this->session->notify(new LogMessage($message, $level));
    }

    public function shutdown(): void
    {
        $this->progress->flush(true);
    }

    public function __destruct()
    {
        $this->shutdown();
    }
}
