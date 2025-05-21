<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler;

use Multitron\Execution\Handler\MasterCache\MasterCacheServer;

final class DefaultIpcHandlerRegistryFactory implements IpcHandlerRegistryFactory
{
    public function __construct(private readonly MasterCacheServer $cacheServer, private readonly ProgressServer $progressServer)
    {
    }


    public function create(): IpcHandlerRegistry
    {
        $registry = new IpcHandlerRegistry();
        $registry->onRequest($this->cacheServer->handleRequest(...));
        $registry->onMessage($this->progressServer->handleProgress(...));
        return $registry;
    }
}
