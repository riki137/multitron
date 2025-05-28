<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use StreamIpc\Message\Message;

final class MasterCacheServer
{
    /** @var array<string, mixed> */
    private array $storage = [];

    public function __construct()
    {
    }

    public function handleRequest(Message $request): ?Message
    {
        if ($request instanceof SmartMasterCacheWriteRequest) {
            return $request->doWrite($this->storage);
        }
        if ($request instanceof SmartMasterCacheReadRequest) {
            return $request->doRead($this->storage);
        }
        return null;
    }

    /**
     * Clears entire cache.
     */
    public function clear(): void
    {
        $this->storage = [];
    }
}
