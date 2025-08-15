<?php

declare(strict_types=1);

namespace Multitron\Comms;

use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\NativeIpcPeer;

final readonly class NativeIpcAdapter implements IpcAdapter
{
    public function __construct(private NativeIpcPeer $peer)
    {
    }

    public function createWorkerSession(?string $connection): IpcSession
    {
        return $this->peer->createStdioSession();
    }

    public function getPeer(): IpcPeer
    {
        return $this->peer;
    }
}
