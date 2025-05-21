<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler;

interface IpcHandlerRegistryFactory
{
    public function create(): IpcHandlerRegistry;
}
