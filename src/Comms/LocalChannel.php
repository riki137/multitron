<?php
declare(strict_types=1);

namespace Multitron\Comms;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use Closure;
use Throwable;

class LocalChannel implements Channel
{
    private array $queue = [];
    private array $onClose = [];
    private bool $closed = false;
    private DeferredFuture $future;

    public function __construct()
    {
        $this->future = new DeferredFuture();
    }

    public function send(mixed $data): void
    {
        if ($this->closed) {
            throw new ChannelException('Channel is closed');
        }

        $this->queue[] = $data;

        if (!$this->future->isComplete()) {
            $this->future->complete();
        }
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        if ($this->closed && empty($this->queue)) {
            throw new ChannelException('Channel is closed');
        }

        if (empty($this->queue)) {
            if ($this->future->isComplete()) {
                $this->future = new DeferredFuture();
            }

            $cancellation?->subscribe(function (Throwable $reason) {
                if (!$this->future->isComplete()) {
                    $this->future->error(new ChannelException('Operation cancelled', 0, $reason));
                }
            });

            $this->future->getFuture()->await();
        }

        if ($this->closed && empty($this->queue)) {
            throw new ChannelException('Channel is closed');
        }

        return array_shift($this->queue);
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function onClose(Closure $onClose): void
    {
        $this->onClose[] = $onClose;
    }

    public function close(): void
    {
        if (!$this->closed) {
            $this->closed = true;

            foreach ($this->onClose as $onClose) {
                $onClose();
            }

            if (!$this->future->isComplete()) {
                $this->future->error(new ChannelException('Channel is closed'));
            }
        }
    }
}

