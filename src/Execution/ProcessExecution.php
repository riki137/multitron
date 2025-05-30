<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Multitron\Console\MultitronWorkerCommand;
use RuntimeException;
use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;

final readonly class ProcessExecution implements Execution
{
    private Process $process;

    private IpcSession $session;

    public function __construct(IpcPeer $ipcPeer)
    {
        /** @var string|null $script */
        $script = $_ENV['MULTITRON_SCRIPTNAME'] ?? $_SERVER['SCRIPT_FILENAME'] ?? null;
        if ($script === null) {
            if (is_array($_SERVER['argv'] ?? null) && is_string($_SERVER['argv'][0] ?? null)) {
                $script = $_SERVER['argv'][0];
            }
            if ($script === null) {
                throw new RuntimeException('Could not determine currently running script filename');
            }
        }
        $this->process = new Process([
            PHP_BINARY,
            $script,
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
        $this->process->kill();
    }
}
