<?php

declare(strict_types=1);

namespace Multitron\Comms;

use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\ProcessExecutionFactory;
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

    public function createExecutionFactory(?int $processBufferSize, float $timeout): ExecutionFactory
    {
        return new ProcessExecutionFactory($this->peer, $processBufferSize, $timeout);
    }
}
