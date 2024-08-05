<?php

declare(strict_types=1);

namespace Multitron\Error;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

class PsrLogErrorHandler implements ErrorHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $taskErrorLevel = LogLevel::ERROR,
        private readonly string $internalErrorLevel = LogLevel::CRITICAL
    ) {
    }

    public function taskError(string $taskId, Throwable $err): string
    {
        $this->logger->log($this->taskErrorLevel, $err->getMessage(), ['taskId' => $taskId, 'exception' => $err]);
        return $err->getMessage();
    }

    public function internalError(Throwable $err): string
    {
        $this->logger->log($this->internalErrorLevel, $err->getMessage(), ['exception' => $err]);
        return $err->getMessage();
    }

}
