<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler;

use Multitron\Execution\Handler\MasterCache\MasterCacheServer;

final class DefaultIpcHandlerRegistryFactory implements IpcHandlerRegistryFactory
{
    /**
     * @param MasterCacheServer $cacheServer   handles cache messages
     * @param ProgressServer    $progressServer handles progress updates
     */
    public function __construct(private readonly MasterCacheServer $cacheServer, private readonly ProgressServer $progressServer)
    {
    }

    /**
     * Create a fresh registry populated with the default cache and progress
     * handlers. The caller is responsible for attaching the registry to a
     * {@see TaskState} before execution begins.
     */
    public function create(): IpcHandlerRegistry
    {
        $registry = new IpcHandlerRegistry();
        $registry->onRequest($this->cacheServer->handleRequest(...));
        $registry->onMessage($this->progressServer->handleProgress(...));
        return $registry;
    }
}
