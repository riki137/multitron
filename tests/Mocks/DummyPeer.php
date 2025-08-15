<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\Transport\MessageTransport;

class DummyPeer extends IpcPeer
{
    public function make(MessageTransport $t): IpcSession
    {
        return $this->createSession($t);
    }

    public function tick(?float $timeout = null): void
    {
    }
}
