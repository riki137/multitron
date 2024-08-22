<?php

declare(strict_types=1);

namespace Multitron\Comms\Data\Message;

enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case NOTICE = 'notice';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
    case ALERT = 'alert';
    case EMERGENCY = 'emergency';

    public function toColor(): string
    {
        return match ($this) {
            self::DEBUG => 'gray',
            self::INFO => 'green',
            self::NOTICE, self::WARNING => 'yellow',
            self::ERROR => 'red',
            default => 'magenta',
        };
    }

    public function isFaulty(): bool
    {
        return match ($this) {
            self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY => true,
            default => false,
        };
    }
}
