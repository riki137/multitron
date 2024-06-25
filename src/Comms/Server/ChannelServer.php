<?php

declare(strict_types=1);

namespace Multitron\Comms\Server;

use RuntimeException;
use Throwable;

class ChannelServer
{
    /**
     * @param ChannelRequestHandler[] $handlers
     */
    public function __construct(private readonly array $handlers)
    {
    }

    public function answer(ChannelRequest $request): ChannelResponse
    {
        $response = $this->makeResponse($request);
        $response->requestId = $request->getRequestId();
        return $response;
    }

    private function makeResponse(ChannelRequest $message): ChannelResponse
    {
        try {
            foreach ($this->handlers as $handler) {
                $response = $handler->handle($message);
                if ($response !== null) {
                    return $response;
                }
            }
        } catch (Throwable $e) {
            return new ErrorResponse($e->getMessage());
        }

        throw new RuntimeException('No handler found for message ' . get_class($message));
    }
}
