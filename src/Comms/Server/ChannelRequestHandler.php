<?php

declare(strict_types=1);

namespace Multitron\Comms\Server;

interface ChannelRequestHandler
{
    public function handle(ChannelRequest $request): ?ChannelResponse;
}
