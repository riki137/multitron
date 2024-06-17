<?php

declare(strict_types=1);

namespace Multitron\Comms\Data\Message;

enum LogLevel: string
{
    case DEBUG = \Psr\Log\LogLevel::DEBUG;
    case INFO = \Psr\Log\LogLevel::INFO;
    case NOTICE = \Psr\Log\LogLevel::NOTICE;
    case WARNING = \Psr\Log\LogLevel::WARNING;
    case ERROR = \Psr\Log\LogLevel::ERROR;
    case CRITICAL = \Psr\Log\LogLevel::CRITICAL;
    case ALERT = \Psr\Log\LogLevel::ALERT;
    case EMERGENCY = \Psr\Log\LogLevel::EMERGENCY;

    public function toColor(): string
    {
        return match ($this) {
            self::DEBUG => 'gray',
            self::INFO => 'green',
            self::NOTICE, self::WARNING => 'yellow',
            self::ERROR => 'red',
            self::CRITICAL, self::ALERT, self::EMERGENCY => 'magenta',
            default => 'blue',
        };
    }
}
