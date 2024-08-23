<?php

declare(strict_types=1);

namespace Multitron\Error;

use Throwable;

final class PlainErrorHandler implements ErrorHandler
{
    public function taskError(string $taskId, Throwable $err): string
    {
        return 'Task ' . $taskId . ' failed: ' . $err->getMessage();
    }

    public function internalError(Throwable $err): string
    {
        return $err->getMessage();
    }
}
