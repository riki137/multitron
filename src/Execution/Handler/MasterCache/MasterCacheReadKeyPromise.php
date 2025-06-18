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
    public function __construct(private ResponsePromise $promise, private string $key)
    {
    }

    /**
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
