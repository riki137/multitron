<?php
declare(strict_types=1);

namespace Multitron\Comms;

use Multitron\Execution\Handler\MasterCache\MasterCacheReadKeyPromise;
use Multitron\Execution\Handler\MasterCache\MasterCacheReadKeysPromise;
use Multitron\Execution\Handler\MasterCache\MasterCacheReadKeysRequest;
use Multitron\Execution\Handler\MasterCache\MasterCacheReadRequest;
use Multitron\Execution\Handler\MasterCache\MasterCacheWriteKeysRequest;
use Multitron\Execution\Handler\MasterCache\MasterCacheWriteRequest;
use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\IpcSession;

/**
 * Thin, type-safe proxy to the Master-Cache server.
 *
 * All methods are fully asynchronous; they return a {@see ResponsePromise}
 * that resolves once the server completes the operation.
 *
 * Typical usage:
 * ```php
 * $client = new MasterCacheClient($session);
 *
 * // Write a nested fragment (auto precedence)
 * $client->write(['user' => ['profile' => ['name' => 'Alice']]])->await(); // await is optional
 *
 * // Read a slice of the cache
 * $client->read(['user' => ['settings', 'profile' => ['name']]])->await()['user']['profile']['name']; // 'Alice'
 *
 * ```
 */
final readonly class MasterCacheClient
{
    public function __construct(private IpcSession $session)
    {
    }

    /**
     * Retrieve one or more keys from the master cache.
     *
     * The **shape** of `$paths` mirrors the shape of the data you want back:
     * scalar entries mark leaf keys; nested arrays drill down.
     *
     * ```php
     * // Fetch "user.profile.name" and the entire "settings" subtree
     * $paths = [
     *     'user' => [
     *         'profile'  => ['name'],
     *         'settings',
     *     ],
     * ];
     * ```
     *
     * @param array<int|string,mixed> $paths
     *        Path specification; see {@see MasterCacheReadKeysRequest} for details.
     * @return MasterCacheReadKeysPromise
     *         Resolves to the requested subset of the cache.
     */
    public function read(array $paths): MasterCacheReadKeysPromise
    {
        return new MasterCacheReadKeysPromise(
            $this->session->request(new MasterCacheReadKeysRequest($paths))
        );
    }

    /**
     * @return MasterCacheReadKeyPromise<mixed>
     */
    public function readKey(string $key): MasterCacheReadKeyPromise
    {
        return new MasterCacheReadKeyPromise(
            $this->session->request(new MasterCacheReadKeysRequest([$key])),
            $key
        );
    }

    /**
     * Enqueue a nested array to be merged into the cache.
     *
     * @param array<int|string,mixed> $data
     *        Structure to merge. Scalars overwrite; sub-arrays merge recursively.
     * @param int|null $depth
     *        `null` lets the request auto-detect a suitable level (max depth of `$data`).
     * @return ResponsePromise
     */
    public function write(array $data, ?int $depth = null): ResponsePromise
    {
        return $this->request(
            (new MasterCacheWriteKeysRequest())->write($data, $depth)
        );
    }

    /**
     * Send a pre-built request (read or write) to the server.
     *
     * Handy when you batch several operations manually before dispatch.
     *
     * @param MasterCacheWriteRequest|MasterCacheReadRequest $request
     * @return ResponsePromise
     */
    public function request(
        MasterCacheWriteRequest|MasterCacheReadRequest $request
    ): ResponsePromise {
        return $this->session->request($request);
    }
}
