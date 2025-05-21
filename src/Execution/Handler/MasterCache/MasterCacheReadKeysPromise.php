<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use PhpStreamIpc\Envelope\ResponsePromise;

final readonly class MasterCacheReadKeysPromise
{
    public function __construct(private ResponsePromise $promise)
    {
    }

    public function await(): array
    {
        $response = $this->promise->await();
        assert($response instanceof MasterCacheReadResponse);
        return $response->data;
    }
}
