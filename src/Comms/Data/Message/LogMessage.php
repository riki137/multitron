<?php
declare(strict_types=1);

namespace Multitron\Comms\Data\Message;

class LogMessage implements Message
{
    public function __construct(
        public readonly string $status,
        public readonly LogLevel $level = LogLevel::INFO
    ) {
    }

    public static function getId(): string
    {
        return 'status';
    }
}
