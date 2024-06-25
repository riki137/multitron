<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Semaphore;

use Amp\Sync\PosixSemaphore;
use Multitron\Comms\Server\ChannelRequest;
use Multitron\Comms\Server\ChannelRequestHandler;
use Multitron\Comms\Server\ChannelResponse;

class SemaphoreHandler implements ChannelRequestHandler
{
    private array $semaphores = [];

    public function handle(ChannelRequest $request): ?ChannelResponse
    {
        if ($request instanceof SemaphoreRequest) {
            $this->semaphores[$request->name] ??= PosixSemaphore::create($request->maxLocks);
            return new SemaphoreResponse($this->semaphores[$request->name]->getKey());
        }

        return null;
    }
}
