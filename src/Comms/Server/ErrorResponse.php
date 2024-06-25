<?php

declare(strict_types=1);

namespace Multitron\Comms\Server;

class ErrorResponse extends ChannelResponse
{
    public function __construct(public readonly string $message)
    {
    }
}
