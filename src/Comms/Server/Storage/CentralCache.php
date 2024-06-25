<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

use Multitron\Comms\Server\ChannelRequest;
use Multitron\Comms\Server\ChannelRequestHandler;
use Multitron\Comms\Server\ChannelResponse;
use Multitron\Comms\Server\OKResponse;

class CentralCache implements ChannelRequestHandler
{
    private array $cache = [];

    public function handle(ChannelRequest $request): ?ChannelResponse
    {
        if ($request instanceof CentralReadRequest) {
            return new CentralReadResponse($request->read($this->cache));
        }

        if ($request instanceof CentralWriteRequest) {
            $request->write($this->cache);
            return new OKResponse();
        }

        return null;
    }
}
