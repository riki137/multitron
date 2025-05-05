<?php

declare(strict_types=1);

namespace Multitron\Comms;

use Multitron\Execution\Handler\MasterCache\MasterCacheClient;
use Multitron\Execution\Handler\ProgressClient;
use PhpStreamIpc\IpcSession;

final readonly class TaskCommunicator
{
    private MasterCacheClient $cache;
    private ProgressClient $progress;

    public function __construct(
        IpcSession $session,
        private array $options,
        ?MasterCacheClient $cache = null,
        ?ProgressClient $progress = null
    ) {
        $this->cache = $cache ?? new MasterCacheClient($session);
        $this->progress = $progress ?? new ProgressClient($session);
    }

    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function cache(): MasterCacheClient
    {
        return $this->cache;
    }

    public function progress(): ProgressClient
    {
        return $this->progress;
    }

}
