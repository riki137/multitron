<?php

declare(strict_types=1);

namespace Multitron\Comms;

use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\IpcSession;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

final readonly class TaskCommunicator
{
    private const DEFAULT_TIMEOUT = 10.0;

    public MasterCacheClient $cache;

    public ProgressClient $progress;

    private float $requestTimeout;

    /**
     * Build a communication helper for tasks running in a worker process.
     * The helper exposes progress reporting and master cache access.
     *
     * @param array<string, mixed> $options command options passed to the worker
     */
    public function __construct(
        private IpcSession $session,
        private array $options,
        ?float $requestTimeout = null,
    ) {
        $this->cache = new MasterCacheClient($session);
        $this->progress = new ProgressClient($session);
        $this->requestTimeout = $requestTimeout ?? self::DEFAULT_TIMEOUT;
    }

    /**
     * Transmit a one-way message to the worker. Notifications do not provide
     * any acknowledgment or response from the other side.
     */
    public function notify(Message $message): void
    {
        $this->session->notify($message);
    }

    /**
     * Forward a request message to the worker and obtain a promise for its
     * eventual reply. Timeouts are applied based on the configured setting.
     */
    public function request(Message $message): ResponsePromise
    {
        return $this->session->request($message, $this->requestTimeout);
    }

    /**
     * Retrieve a single option value that was passed to the worker at
     * construction time. Returns `null` if the option was not supplied.
     */
    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Return all options that were provided when constructing this
     * communicator.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Forward a textual log entry to the worker so that it can be captured or
     * displayed remotely.
     */
    public function log(string $message, string $level = 'info'): void
    {
        $this->session->notify(new LogMessage($message, $level));
    }

    /**
     * Flush progress updates and deliver any pending warnings before the
     * communicator goes out of scope.
     */
    public function shutdown(): void
    {
        $this->progress->shutdown();
    }
}
