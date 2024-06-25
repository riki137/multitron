<?php

declare(strict_types=1);

namespace Multitron\Comms\Local;

use Amp\Cancellation;
use Amp\Serialization\SerializationException;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;

class LocalChannelCombination implements Channel
{
    public function __construct(
        private readonly LocalChannel $receiveFrom,
        private readonly LocalChannel $sendTo,
        private readonly bool $shouldClose = false
    ) {
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        return $this->receiveFrom->receive($cancellation);
    }

    public function send(mixed $data): void
    {
        $this->sendTo->send($data);
    }

    public function close(): void
    {
        if ($this->shouldClose) {
            $this->receiveFrom->close();
            $this->sendTo->close();
        }
    }

    public function isClosed(): bool
    {
        return $this->receiveFrom->isClosed() || $this->sendTo->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->receiveFrom->onClose($onClose);
        $this->sendTo->onClose($onClose);
    }
}
