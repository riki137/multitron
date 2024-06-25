<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Semaphore;

use Multitron\Comms\Server\ChannelRequest;

class SemaphoreRequest extends ChannelRequest
{
    public function __construct(public readonly string $name, public readonly int $maxLocks = 1)
    {
    }
}
