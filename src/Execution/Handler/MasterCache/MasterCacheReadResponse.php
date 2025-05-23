<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use PhpStreamIpc\Message\Message;

final readonly class MasterCacheReadResponse implements Message
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public array $data)
    {
    }
}
