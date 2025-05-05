<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use Amp\Future;
use PhpStreamIpc\IpcSession;
use function Amp\async;

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
     * @return Future<array<string, mixed>> A future resolving with the fetched data.
     */
    public function read(array $paths): Future
    {
        return $this->session->request(new MasterCacheReadRequest($paths))
            ->map(static fn(MasterCacheReadResponse $response) => $response->data);
    }

    /**
     * Sets (overwrites) a value at the specified path in the master cache.
     *
     * @param string|string[] $path The path segments. A single string is treated as a top-level key.
     * @param mixed $value The value to set.
     * @return Future<void> A future that completes when the operation is acknowledged by the server.
     */
    public function set(string|array $path, mixed $value): Future
    {
        $segments = is_string($path) ? [$path] : $path;
        $request = (new MasterCacheWriteRequest())->write($segments, $value);
        return $this->write($request);
    }

    /**
     * Merges an array value into the existing data at the specified path in the master cache.
     * If the existing value is not an array, it will be overwritten.
     *
     * @param string|string[] $path The path segments. A single string is treated as a top-level key.
     * @param array $value The array value to merge.
     * @return Future<void> A future that completes when the operation is acknowledged by the server.
     */
    public function merge(string|array $path, array $value): Future
    {
        $segments = is_string($path) ? [$path] : $path;
        $request = (new MasterCacheWriteRequest())->merge($segments, $value);
        return $this->write($request);
    }

    /**
     * Sends a pre-constructed write request (potentially containing multiple operations)
     * to the master cache server.
     *
     * @param MasterCacheWriteRequest $request The write request object.
     * @return Future<void> A future that completes when the operation is acknowledged by the server.
     */
    public function write(MasterCacheWriteRequest $request): Future
    {
        return $this->session->request($request)
            ->map(static fn(MasterCacheWriteResponse $response) => $response);
    }
}
