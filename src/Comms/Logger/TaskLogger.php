<?php

declare(strict_types=1);

namespace Multitron\Comms\Logger;

use Throwable;

interface TaskLogger
{
    public function error(Throwable $error): void;

    public function info(string $taskId, string $message): void;
}
