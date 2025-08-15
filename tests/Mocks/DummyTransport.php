<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use StreamIpc\Message\Message;
use StreamIpc\Transport\MessageTransport;

class DummyTransport implements MessageTransport
{
    public array $sent = [];

    public function send(Message $message): void
    {
        $this->sent[] = $message;
    }

    public function getReadStreams(): array
    {
        return [];
    }

    public function readFromStream($stream): array
    {
        return [];
    }
}
