<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Amp\Process\Process;
use Closure;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\IpcSession;

final readonly class ProcessExecution implements Execution
{
    private Process $process;
    private IpcSession $session;

    public function __construct(IpcPeer $ipcPeer, string $bootstrapPath) {
        $this->process = Process::start([
            PHP_BINARY,
            __DIR__ . '/multitron_worker.php',
            '--bootstrap=' . escapeshellarg($bootstrapPath),
        ]);
        $this->session = $ipcPeer->createProcessSession($this->process);
    }

    public function getSession(): IpcSession
    {
        return $this->session;
    }

    public function statusCode(): ?int
    {
        return $this->process->isRunning() ? null : $this->process->join();
    }

    public function stop(): void
    {
        $this->process->kill();
    }

    public function await(): int
    {
        return $this->process->join();
    }

    public function __destruct()
    {
        if ($this->process->isRunning()) {
            $this->process->kill();
        }
    }
}
