<?php
declare(strict_types=1);

namespace Multitron\Util;

use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Future;
use Closure;
use function Amp\async;
use function Amp\delay;

class Throttle
{
    private ?Future $future = null;

    private bool $complete = true;

    private DeferredCancellation $cancel;

    private float $lastCall = 0;

    public function __construct(private readonly Closure $callback, private readonly int $ms = 10)
    {
        $this->cancel = new DeferredCancellation();
    }

    public function call(bool $force = false): void
    {
        if ($force || microtime(true) * 1000 - $this->lastCall > $this->ms) {
            $this->lastCall = microtime(true) * 1000;
            ($this->callback)();
            return;
        }
        if ($this->complete) {
            $this->complete = false;
            $this->future = async(function () {
                try {
                    delay($this->ms / 1000, true, $this->cancel->getCancellation());
                    $this->lastCall = microtime(true) * 1000;
                    async($this->callback);
                } catch (CancelledException) {
                } finally {
                    $this->complete = true;
                }
            });
        }
    }

    public function shutdown(): void
    {
        $this->cancel->cancel();
        if ($this->future !== null && !$this->future->isComplete()) {
            $this->future->ignore();
            ($this->callback)();
        }
    }
}
