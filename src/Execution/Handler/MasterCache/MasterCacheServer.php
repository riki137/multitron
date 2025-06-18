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

    /**
     * Dispatch the incoming cache message to the appropriate request handler.
     * Write requests modify the internal storage while read requests return a
     * message containing the selected values.
     */
    public function handleRequest(Message $request): ?Message
    {
        if ($request instanceof MasterCacheWriteRequest) {
            $request->doWrite($this->storage);
            return new MasterCacheWriteResponse();
        }
        if ($request instanceof MasterCacheReadRequest) {
            return $request->doRead($this->storage);
        }
        return null;
    }

    /**
     * Remove all entries from the cache. Mainly useful in unit tests where
     * persistent state would cause interference between runs.
     */
    public function clear(): void
    {
        $this->storage = [];
    }
}
