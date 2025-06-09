<?php

declare(strict_types=1);

namespace Multitron\Comms;

use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\IpcSession;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

final readonly class TaskCommunicator
{
    public MasterCacheClient $cache;

    public ProgressClient $progress;

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

    public function log(string $message, string $level = 'info'): void
    {
        $this->session->notify(new LogMessage($message, $level));
    }

    public function shutdown(): void
    {
        $this->progress->shutdown();
    }
}
