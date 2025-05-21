<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use PhpStreamIpc\Message\Message;

final class MasterCacheServer
{
    /** @var array<string, mixed> */
    private array $storage = [];

    public function __construct()
    {
    }

    public function handleRequest(SmartMasterCacheWriteRequest|SmartMasterCacheReadRequest $request): Message
    {
        if ($request instanceof SmartMasterCacheWriteRequest) {
            return $request->doWrite($this->storage);
        }
        return $request->doRead($this->storage);
    }

    /**
     * Clears entire cache.
     */
    public function clear(): void
    {
        $this->storage = [];
    }
}
