<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Multitron\Console\MultitronWorkerCommand;
use RuntimeException;
use StreamIpc\IpcSession;
use StreamIpc\NativeIpcPeer;

final readonly class ProcessExecution implements Execution
{
    private Process $process;

    private IpcSession $session;

    /**
     * Spawn a worker process and establish an IPC session used for
     * communication with it. The same script file is executed again in worker
     * mode to bootstrap the environment.
     */
    public function __construct(NativeIpcPeer $ipcPeer)
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

    /**
     * @return array{exitCode: int|null, stdout: string, stderr: string}
     */
    public function kill(): array
    {
        $this->process->kill();
        return [
            'exitCode' => $this->process->getExitCode(),
            'stdout' => (string)stream_get_contents($this->process->getStdout()),
            'stderr' => (string)stream_get_contents($this->process->getStderr()),
        ];
    }
}
