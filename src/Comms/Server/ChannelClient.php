<?php

declare(strict_types=1);

namespace Multitron\Comms\Server;

use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use function Amp\async;

class ChannelClient
{
    /** @var DeferredFuture[] */
    private array $futures = [];

    private DeferredCancellation $cancel;

    public function __construct(private readonly Channel $channel)
    {
        $this->cancel = new DeferredCancellation();
    }

    public function start(): void
    {
        async(function () {
            try {
                while (!$this->cancel->isCancelled()) {
                    $response = $this->channel->receive($this->cancel->getCancellation());
                    assert($response instanceof ChannelResponse);
                    $future = $this->futures[$response->requestId];
                    if ($response instanceof ErrorResponse) {
                        $future->error(new ChannelServerException($response));
                    } else {
                        $future->complete($response);
                    }
                    unset($this->futures[$response->requestId]);
                }
            } catch (ChannelException $e) {
                if (!str_contains($e->getMessage(), 'Channel source closed')) {
                    throw $e;
                }
            }
        })->ignore();
    }

    public function send(ChannelRequest $request): Future
    {
        $future = $this->futures[$request->getRequestId()] = new DeferredFuture();
        $this->channel->send($request);
        return $future->getFuture();
    }

    public function shutdown(): void
    {
        $this->cancel->cancel();
    }
}
