<?php

declare(strict_types=1);

namespace Multitron\Comms\Server;

use Amp\DeferredFuture;
use Amp\Future;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use function Amp\async;

class ChannelClient
{
    /** @var DeferredFuture[] */
    private array $futures = [];
    private Future $cycle;

    public function __construct(private readonly Channel $channel)
    {
    }

    public function start(): void
    {
        $this->cycle = async(function () {
            try {
            while (true) {
                $response = $this->channel->receive();
                assert($response instanceof ChannelResponse);
                $future = $this->futures[$response->requestId];
                if ($response instanceof ErrorResponse) {
                    $future->error(new ChannelServerException($response));
                } else {
                    $future->complete($response);
                }
            }} catch (ChannelException $e) {
                if (!str_contains($e->getMessage(), 'Channel source closed')) {
                    throw $e;
                }
            }
        });
    }

    public function send(ChannelRequest $request): ChannelResponse
    {
        $future = $this->futures[$request->getRequestId()] = new DeferredFuture();
        $this->channel->send($request);
        return $future->getFuture()->await();
    }
}
