<?php
declare(strict_types=1);

namespace Multitron\Util;

use Amp\Future;
use Closure;
use function Amp\async;
use function Amp\delay;

class Throttle
{
    private ?Future $future = null;

    public function __construct(private readonly Closure $callback, private readonly int $ms = 10)
    {
    }

    public function call(bool $force = false): void
    {
        if ($force) {
            ($this->callback)();
            return;
        }
        if ($this->future === null || $this->future->isComplete()) {
            $this->future = async(function () {
                delay($this->ms / 1000);
                async($this->callback);
            });
        }
    }
}
