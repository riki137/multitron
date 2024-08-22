<?php

declare(strict_types=1);

namespace Multitron\Comms\Server;

abstract class ChannelRequest
{
    private static ?int $pid = null;

    private static int $idCounter = 0;

    private ?string $requestId = null;

    public function getRequestId(): string
    {
        if ($this->requestId === null) {
            self::$pid ??= (getmypid() ?: mt_rand());
            $this->requestId = self::$pid . ':' . ++self::$idCounter;
        }
        return $this->requestId;
    }
}
