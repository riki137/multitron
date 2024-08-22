<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

use Multitron\Comms\Server\ChannelResponse;

/**
 * @template T
 */
class CentralReadResponse extends ChannelResponse
{
    /**
     * @param T $data
     */
    public function __construct(public mixed &$data)
    {
    }
}
