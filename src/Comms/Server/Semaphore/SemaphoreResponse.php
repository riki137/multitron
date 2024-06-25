<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Semaphore;

use Amp\Sync\PosixSemaphore;
use Multitron\Comms\Server\ChannelResponse;

class SemaphoreResponse extends ChannelResponse
{
    public function __construct(private readonly int $key)
    {
    }

    public function getSemaphore(): PosixSemaphore
    {
        return PosixSemaphore::use($this->key);
    }
}
