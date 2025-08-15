<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use StreamIpc\Message\Message;
use StreamIpc\Transport\MessageTransport;

class OrchestratorTransport implements MessageTransport
{
    public function send(Message $message): void
    {
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
