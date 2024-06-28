<?php

declare(strict_types=1);

namespace Multitron\Error;

use Throwable;

interface ErrorHandler
{
    public function taskError(string $taskId, Throwable $err): string;

    public function internalError(Throwable $err): string;
}
