<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Multitron\Console\MultitronWorkerCommand;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\IpcSession;

final readonly class ProcessExecution implements Execution
{
    private Process $process;

    private IpcSession $session;

    public function __construct(IpcPeer $ipcPeer)
    {
        $this->process = new Process([
            PHP_BINARY,
            $_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['argv'][0],
            MultitronWorkerCommand::NAME,
        ]);
        $this->session = $ipcPeer->createStreamSession(
            $this->process->getStdin(),
            $this->process->getStdout(),
            $this->process->getStderr()
        );
    }

    public function getSession(): IpcSession
    {
        return $this->session;
    }

    public function getExitCode(): ?int
    {
        return $this->process->getExitCode();
    }

    public function kill(): void
    {
        $this->process->kill();
    }

    public function __destruct()
    {
        if ($this->process->isRunning()) {
            $this->process->kill();
        }
    }
}
