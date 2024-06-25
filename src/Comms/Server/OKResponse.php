<?php

declare(strict_types=1);

namespace Multitron\Comms\Server;

class OKResponse extends ChannelResponse
{
    public function __construct(public readonly ?string $message = null)
    {
    }
}
