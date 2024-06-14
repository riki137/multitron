<?php

declare(strict_types=1);

namespace Multitron\Process;

use Closure;

interface RunningTask
{
    public function await(): mixed;

    public function finally(Closure $closure): void;

    public function cancel(): void;

    public function getCentre(): TaskCentre;
}
