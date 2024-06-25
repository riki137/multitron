<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

use Multitron\Comms\Server\ChannelRequest;

abstract class CentralWriteRequest extends ChannelRequest
{
    abstract public function write(array &$cache): void;
}
