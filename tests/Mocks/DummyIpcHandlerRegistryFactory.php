<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;

class DummyIpcHandlerRegistryFactory implements IpcHandlerRegistryFactory
{
    public function create(): IpcHandlerRegistry
    {
        return new IpcHandlerRegistry();
    }
}

