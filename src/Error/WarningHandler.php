<?php

declare(strict_types=1);

namespace Multitron\Error;

use Multitron\Comms\Data\Message\LogLevel;
use Multitron\Comms\TaskCommunicator;

final class WarningHandler
{
    private const WARNINGS = E_WARNING | E_USER_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_NOTICE | E_USER_NOTICE | E_STRICT | E_DEPRECATED | E_USER_DEPRECATED;

    private array $reportedErrors = [];

    public function __construct(private readonly TaskCommunicator $comm)
    {
    }

    public function handle(int $errorNumber, string $errorString, string $file, int $line): bool
    {
        // Check if the error was silenced by the @ operator
        if (!(error_reporting() & $errorNumber)) {
            // If the error was silenced with @, we ignore it
            return false;
        }

        $errorCode = $this->errorToString($errorNumber);
        $errorMessage = "[$errorCode] $errorString in $file on line $line";
        if (!isset($this->reportedErrors[$errorMessage])) {
            $level = ($errorNumber & self::WARNINGS) ? LogLevel::WARNING : LogLevel::ERROR;
            $this->comm->log($errorMessage, $level);
        }

        $this->reportedErrors[$errorMessage] = true;
        return false;
    }

    private function errorToString(int $errno): string
    {
        return match ($errno) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'UNKNOWN',
        };
    }
}
