<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Error\ErrorHandler;
use Multitron\Error\PlainErrorHandler;

class MultitronConfig
{
    private ErrorHandler $errorHandler;

    public function __construct(
        private readonly string $bootstrapPath,
        private readonly ?int $concurrentTasks,
        ?ErrorHandler $errorHandler = null
    ) {
        $this->errorHandler = $errorHandler ?? new PlainErrorHandler();
    }

    public function getBootstrapPath(): string
    {
        return $this->bootstrapPath;
    }

    public function getConcurrentTasks(): ?int
    {
        return $this->concurrentTasks;
    }

    public function getErrorHandler(): ErrorHandler
    {
        return $this->errorHandler;
    }
}
