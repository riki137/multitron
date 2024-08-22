<?php

declare(strict_types=1);

namespace Multitron\Error;

use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;

class TracyErrorHandler implements ErrorHandler
{
    public function __construct()
    {
        Debugger::$strictMode = false;
    }

    public function taskError(string $taskId, Throwable $err): string
    {
        $filename = mb_ereg_replace('([^\w\s\d\-_~,;\[\]\(\).])', '_', 'task~' . $taskId);
        $file = Debugger::log($err, is_string($filename) ? $filename : ILogger::INFO);
        if (!is_string($file)) {
            $file = 'unknown';
        } else {
            $file = basename($file);
        }
        return "{$err->getMessage()} (file: $file)";
    }

    public function internalError(Throwable $err): string
    {
        $file = Debugger::log($err, 'multitron');
        return "{$err->getMessage()} (file: $file)";
    }
}
