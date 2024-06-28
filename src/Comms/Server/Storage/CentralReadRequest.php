<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

use Multitron\Comms\Server\ChannelRequest;

abstract class CentralReadRequest extends ChannelRequest
{
    /**
     * @param array<string, mixed[]> $cache
     */
    abstract public function &read(array &$cache): array;
}
