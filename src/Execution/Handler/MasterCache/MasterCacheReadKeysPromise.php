<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use LogicException;
use PhpStreamIpc\Envelope\ResponsePromise;

final readonly class MasterCacheReadKeysPromise
{
    public function __construct(private ResponsePromise $promise)
    {
    }

    public function await(): array
    {
        $response = $this->promise->await();
        if (!$response instanceof MasterCacheReadResponse) {
            throw new LogicException('Unexpected response type: ' . get_debug_type($response));
        }
        return $response->data;
    }
}
