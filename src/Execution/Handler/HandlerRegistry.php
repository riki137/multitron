<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler;

use Closure;
use function spl_object_id;

final class HandlerRegistry
{
    private array $handlers = [];

    public function addHandler(Closure $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function removeHandler(Closure $handler): void
    {
        foreach ($this->handlers as $i => $one) {
            if (spl_object_id($one) === spl_object_id($handler)) {
                unset($this->handlers[$i]);
                break;
            }
        }
    }

    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
