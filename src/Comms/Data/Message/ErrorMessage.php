<?php

declare(strict_types=1);

namespace Multitron\Comms\Data\Message;

class ErrorMessage implements Message
{
    public function __construct(
        public readonly string $message
    ) {
    }
}
