<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

use Multitron\Comms\Server\ChannelResponse;

class CentralReadResponse extends ChannelResponse
{
    public function __construct(public ?array &$data)
    {
    }
}
