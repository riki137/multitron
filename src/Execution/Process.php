<?php

declare(strict_types=1);

namespace Multitron\Execution;

use RuntimeException;

class Process
{
    /** @var resource */
    private $process;

    /** @var array<int, resource> */
    private array $pipes = [];

    private ?int $exitCode = null;

    /**
     * @param list<string> $command Array of command + args
     * @param string|null $cwd Working directory
     * @param array<string, string>|null $env Environment overrides
     */
    public function __construct(array $command, ?string $cwd = null, ?array $env = null)
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open(
            $command,
            $descriptors,
            $this->pipes,
            $cwd,
            $env,
            ['bypass_shell' => true]
        );

        if (!is_resource($proc)) {
            throw new RuntimeException('Failed to start process: ' . implode(' ', $command));
        }
        $this->process = $proc;
        if (count($this->pipes) !== 3) {
            throw new RuntimeException('Process pipes were not set up correctly');
        }
    }

    /** @return resource Writable stream for stdin */
    public function getStdin()
    {
        return $this->pipes[0];
    }

    /** @return resource Readable stream for stdout */
    public function getStdout()
    {
        return $this->pipes[1];
    }

    /** @return resource Readable stream for stderr */
    public function getStderr()
    {
        return $this->pipes[2];
    }

    /**
     * @return bool True if the process is still running
     */
    public function isRunning(): bool
    {
        if ($this->exitCode !== null) {
            return false;
        }
        $status = proc_get_status($this->process);
        return $status['running'];
    }

    /**
     * @return int|null Exit code once the process has finished, or null if still running
     */
    public function getExitCode(): ?int
    {
        if ($this->exitCode !== null) {
            return $this->exitCode;
        }
        $status = proc_get_status($this->process);
        if ($status['running']) {
            return null;
        }
        // store it so future calls don’t need proc_get_status again
        $this->exitCode = $status['exitcode'];
        return $this->exitCode;
    }

    /**
     * Send a signal to the process (default SIGKILL)
     *
     * @param int $signal
     * @return void
     */
    public function kill(int $signal = SIGKILL): void
    {
        if (is_resource($this->process) && !proc_terminate($this->process, $signal)) {
            proc_close($this->process);
        }
    }

    /**
     * Close pipes and wait for exit. Returns the exit code.
     *
     * @return int
     */
    public function close(): int
    {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        return $this->exitCode = proc_close($this->process);
    }

    public function __destruct()
    {
        $this->kill();
    }
}
