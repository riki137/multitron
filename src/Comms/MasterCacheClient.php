<?php

declare(strict_types=1);

namespace Multitron\Comms;

use Multitron\Execution\Handler\MasterCache\MasterCacheReadKeysPromise;
use Multitron\Execution\Handler\MasterCache\MasterCacheReadRequest;
use Multitron\Execution\Handler\MasterCache\MasterCacheWriteRequest;
use Multitron\Execution\Handler\MasterCache\SmartMasterCacheReadRequest;
use Multitron\Execution\Handler\MasterCache\SmartMasterCacheWriteRequest;
use PhpStreamIpc\Envelope\ResponsePromise;
use PhpStreamIpc\IpcSession;

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
     * @param array $paths Structure defining keys to fetch. See MasterCacheReadRequest for details.
     *                     Example: ["key1", "key2" => ["nested_key"]]
     * @return MasterCacheReadKeysPromise A promise resolving with the fetched data.
     */
    public function readKeys(array $paths): MasterCacheReadKeysPromise
    {
        return new MasterCacheReadKeysPromise($this->session->request(new SmartMasterCacheReadRequest($paths)));
    }

    public function read(MasterCacheReadRequest $request): ResponsePromise
    {
        return $this->session->request($request);
    }

    /**
     * Sets (overwrites) a value at the specified path in the master cache.
     *
     * @param string|string[] $path The path segments. A single string is treated as a top-level key.
     * @param mixed $value The value to set.
     * @return ResponsePromise A promise that resolves when the operation is acknowledged by the server.
     */
    public function set(string|array $path, mixed $value): ResponsePromise
    {
        $segments = is_string($path) ? [$path] : $path;
        $request = (new SmartMasterCacheWriteRequest())->write($segments, $value);
        return $this->write($request);
    }

    /**
     * Merges an array value into the existing data at the specified path in the master cache.
     * If the existing value is not an array, it will be overwritten.
     *
     * @param string|string[] $path The path segments. A single string is treated as a top-level key.
     * @param array $value The array value to merge.
     * @return ResponsePromise A promise that resolves when the operation is acknowledged by the server.
     */
    public function merge(string|array $path, array $value): ResponsePromise
    {
        $segments = is_string($path) ? [$path] : $path;
        $request = (new SmartMasterCacheWriteRequest())->merge($segments, $value);
        return $this->write($request);
    }

    /**
     * Sends a pre-constructed write request (potentially containing multiple operations)
     * to the master cache server.
     *
     * @param SmartMasterCacheWriteRequest $request The write request object.
     * @return ResponsePromise A promise that resolves when the operation is acknowledged by the server.
     */
    public function write(MasterCacheWriteRequest $request): ResponsePromise
    {
        return $this->session->request($request);
    }
}
