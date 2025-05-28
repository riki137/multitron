<?php
namespace Multitron\Tests\Fixtures;

use StreamIpc\Transport\MessageTransport;
use StreamIpc\Message\Message;
use StreamIpc\IpcSession;

final class FakeTransport implements MessageTransport
{
    /** @var Message[] */
    public array $sent = [];
    /** @var array<int, float|null> */
    public array $ticks = [];
    /** @var IpcSession[][] */
    public array $sessionArgs = [];

    public function send(Message $message): void
    {
        $this->sent[] = $message;
    }

    public function tick(array $sessions, ?float $timeout = null): void
    {
        $this->ticks[] = $timeout;
        $this->sessionArgs[] = $sessions;
    }
}
