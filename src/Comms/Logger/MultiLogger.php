<?php

declare(strict_types=1);

namespace Multitron\Comms\Logger;

use Throwable;

class MultiLogger implements TaskLogger
{
    private array $loggers;

    public function __construct(
        TaskLogger ...$loggers
    ) {
        $this->loggers = $loggers;
    }

    public function error(Throwable $error): void
    {
        foreach ($this->loggers as $logger) {
            $logger->error($error);
        }
    }

    public function info(string $taskId, string $message): void
    {
        foreach ($this->loggers as $logger) {
            $logger->info($taskId, $message);
        }
    }
}
