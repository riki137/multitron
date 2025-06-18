<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use LogicException;
use StreamIpc\Envelope\ResponsePromise;

final readonly class MasterCacheReadKeysPromise
{
    public function __construct(private readonly ResponsePromise $promise)
    {
    }

    /**
     * Block until the read request completes and return the full set of
     * retrieved values keyed by cache identifier.
     *
     * @return array<string, mixed>
     */
    public function await(): array
    {
        $response = $this->promise->await();
        if (!$response instanceof MasterCacheReadResponse) {
            throw new LogicException('Unexpected response type: ' . get_debug_type($response));
        }
        return $response->data;
    }

    /**
     * Convenience wrapper around {@see await()} returning only one entry from
     * the resulting dataset.
     */
    public function get(string $key): mixed
    {
        return $this->await()[$key] ?? null;
    }
}
