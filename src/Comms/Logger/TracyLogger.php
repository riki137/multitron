<?php

declare(strict_types=1);

namespace Multitron\Comms\Logger;

use Throwable;
use Tracy\Debugger;

class TracyLogger implements TaskLogger
{
    public function error(Throwable $error): void
    {
        Debugger::log($error, Debugger::EXCEPTION);
    }

    public function info(string $taskId, string $message): void
    {
        Debugger::log($message);
    }
}
