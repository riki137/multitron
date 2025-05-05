<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Multitron\Message\ContainerLoadedMessage;
use PhpStreamIpc\IpcPeer;
use function Amp\async;
use function Amp\Future\await;

final class ProcessFactory
{
    /** @var ProcessExecution[] */
    private array $processes = [];

    public function __construct(
        private readonly string $bootstrapPath,
        private readonly int $processBufferSize,
        private readonly IpcPeer $ipcPeer,
    ) {
        $futures = [];
        foreach (range(1, $this->processBufferSize) as $_) {
            $futures[] = async($this->create(...));
        }
        await($futures);
    }

    private function create(): ProcessExecution
    {
        $process = new ProcessExecution($this->ipcPeer, $this->bootstrapPath);

        $message = $process->getSession()->receive()->await();
        if (!$message instanceof ContainerLoadedMessage) {
            throw new \RuntimeException(
                'Multitron container failed to load, received ' . get_debug_type($message)
            );
        }

        $this->processes[] = $process;
        return $process;
    }

    public function obtain(): ProcessExecution
    {
        if (empty($this->processes)) {
            return $this->create();
        }

        async($this->create(...));
        return array_shift($this->processes);
    }

    public function shutdown(): void
    {
        foreach ($this->processes as $process) {
            $process->stop();
        }
        $this->processes = [];
    }
}
