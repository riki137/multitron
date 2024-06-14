<?php

declare(strict_types=1);

namespace Multitron\Comms\Logger;

use Psr\Log\LoggerInterface;
use Throwable;

class PsrTaskLogger implements TaskLogger
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function error(Throwable $error): void
    {
        $this->logger->error($error->getMessage(), ['exception' => $error]);
    }

    public function info(string $taskId, string $message): void
    {
        $this->logger->info($message, ['task_id' => $taskId]);
    }
}
