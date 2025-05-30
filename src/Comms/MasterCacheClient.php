<?php

declare(strict_types=1);

namespace Multitron\Comms;

use Multitron\Execution\Handler\MasterCache\MasterCacheReadKeysPromise;
use Multitron\Execution\Handler\MasterCache\MasterCacheReadKeysRequest;
use Multitron\Execution\Handler\MasterCache\MasterCacheReadRequest;
use Multitron\Execution\Handler\MasterCache\MasterCacheWriteKeysRequest;
use Multitron\Execution\Handler\MasterCache\MasterCacheWriteRequest;
use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\IpcSession;

/**
 * Client-side interface for interacting with the MasterCacheServer over IPC.
 */
final readonly class MasterCacheClient
{
    public function __construct(private IpcSession $session)
    {
    }

    /**
     * Reads data from the master cache based on the specified paths.
     *
     * @param array<int|string, mixed> $paths Structure defining keys to fetch. See MasterCacheReadRequest for details.
     *                                             Example: ["key1", "key2" => ["nested_key"]]
     * @return MasterCacheReadKeysPromise A promise resolving with the fetched data.
     */
    public function readKeys(array $paths): MasterCacheReadKeysPromise
    {
        return new MasterCacheReadKeysPromise($this->session->request(new MasterCacheReadKeysRequest($paths)));
    }

    public function read(MasterCacheReadRequest $request): ResponsePromise
    {
        return $this->session->request($request);
    }

    /**
     * Sets (overwrites) a value at the specified path in the master cache.
     *
     * @param string $dotPath Dot-notation path to the value to set.
     * @param mixed $value The value to set.
     * @return ResponsePromise A promise that resolves when the operation is acknowledged by the server.
     */
    public function write(string $dotPath, mixed $value): ResponsePromise
    {
        return $this->request((new MasterCacheWriteKeysRequest())->write($dotPath, $value));
    }

    public function writeFast(int $level, array $path, mixed $value): ResponsePromise
    {
        return $this->request((new MasterCacheWriteKeysRequest())->writeFast($level, $path, $value));
    }

    /**
     * Merges an array value into the existing data at the specified path in the master cache.
     * If the existing value is not an array, it will be overwritten.
     *
     * @param string $dotPath Dot-notation path to the value to merge.
     * @param array<string, mixed> $value The array value to merge.
     * @return ResponsePromise A promise that resolves when the operation is acknowledged by the server.
     */
    public function merge(string $dotPath, array $value): ResponsePromise
    {
        return $this->request((new MasterCacheWriteKeysRequest())->merge($dotPath, $value));
    }

    public function mergeFast(int $level, array $path, array $new): ResponsePromise
    {
        return $this->request((new MasterCacheWriteKeysRequest())->mergeFast($level, $path, $new));
    }

    /**
     * Sends a pre-constructed write request (potentially containing multiple operations)
     * to the master cache server.
     *
     * @param MasterCacheWriteKeysRequest $request The write request object.
     * @return ResponsePromise A promise that resolves when the operation is acknowledged by the server.
     */
    public function request(MasterCacheWriteRequest $request): ResponsePromise
    {
        return $this->session->request($request);
    }
}
