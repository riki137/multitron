<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use LogicException;
use StreamIpc\Envelope\ResponsePromise;

/**
 * @template T
 */
final readonly class MasterCacheReadKeyPromise
{
    /**
     * @param ResponsePromise $promise underlying response promise
     * @param string          $key     key to retrieve from the response
     */
    public function __construct(private ResponsePromise $promise, private string $key)
    {
    }

    /**
     * Wait for the remote read to finish and extract the value for the
     * requested key. Returns `null` when the key was not present in the
     * remote cache.
     *
     * @return T|null
     */
    public function await(): mixed
    {
        $response = $this->promise->await();
        if (!$response instanceof MasterCacheReadResponse) {
            throw new LogicException('Unexpected response type: ' . get_debug_type($response));
        }
        return $response->data[$this->key] ?? null;
    }
}
