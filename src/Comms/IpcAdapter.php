<?php

declare(strict_types=1);

namespace Multitron\Comms;

use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;

interface IpcAdapter
{
    public function createWorkerSession(?string $connection): IpcSession;

    public function getPeer(): IpcPeer;
}
