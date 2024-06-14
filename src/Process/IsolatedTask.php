<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\DeferredCancellation;
use Amp\Parallel\Worker\Execution;
use Closure;

class IsolatedTask implements RunningTask
{
    private TaskCentre $centre;

    public function __construct(
        private readonly Execution $exec,
        private readonly DeferredCancellation $cancel,
    ) {
        $this->centre = new TaskCentre($exec->getChannel(), $this->cancel->getCancellation());
    }

    public function await(): int
    {
        return $this->exec->await();
    }

    public function finally(Closure $closure): void
    {
        $this->exec->getFuture()->finally($closure);
    }

    public function cancel(): void
    {
        $this->cancel->cancel();
    }

    public function getCentre(): TaskCentre
    {
        return $this->centre;
    }
}
