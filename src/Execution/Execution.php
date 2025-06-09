<?php

declare(strict_types=1);

namespace Multitron\Execution;

use StreamIpc\IpcSession;

interface Execution
{
    public function getSession(): IpcSession;

    public function getExitCode(): ?int;

    public function kill(): array;
}
