<?php
declare(strict_types=1);

namespace Multitron\Comms\Data\Message;

class LogMessage implements Message
{
    private readonly int $createdAt;

    public function __construct(
        public readonly string $message,
        public readonly LogLevel $level = LogLevel::INFO
    ) {
        $this->createdAt = time();
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }
}
