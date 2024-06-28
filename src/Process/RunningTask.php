<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\Future;

interface RunningTask
{
    public function getFuture(): Future;

    public function getCentre(): TaskCentre;
}
