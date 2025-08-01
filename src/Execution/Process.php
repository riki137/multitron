<?php
declare(strict_types=1);

namespace Multitron\Execution;

use RuntimeException;
use function is_resource;

/**
 * Thin, race-free wrapper around proc_open().
 *
 *  • getExitCode()  – non-blocking; returns null while running.
 *  • wait()         – blocking; always returns the real exit code.
 *  • close()        – idempotent; closes pipes + reaps child; returns exit code.
 *
 * @psalm-type Pipes = array{0:resource,1:resource,2:resource}
 */
class Process
{
    /** @var resource */
    private $process;

    /** @var Pipes|never[] */
    private array $pipes = [];

    /** @var int<0,255>|null  */
    private ?int $exitCode = null;

    private bool $closed = false;

    /**
     * @param list<string> $cmd
     * @param string|null $cwd
     * @param array<string,string>|null $env
     */
    public function __construct(array $cmd, ?string $cwd = null, ?array $env = null)
    {
        $proc = proc_open(
            $cmd,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $this->pipes,
            $cwd,
            $env,
            ['bypass_shell' => true]
        );

        if (!is_resource($proc)) {
            throw new RuntimeException('Failed to start process: ' . implode(' ', $cmd));
        }
        $this->process = $proc;
        if (count($this->pipes) !== 3) {
            throw new RuntimeException('Process pipes were not set up correctly');
        }
    }

    /** @return resource */
    public function getStdin()
    {
        return $this->pipes[0];
    }

    /** @return resource */
    public function getStdout()
    {
        return $this->pipes[1];
    }

    /** @return resource */
    public function getStderr()
    {
        return $this->pipes[2];
    }

    public function isRunning(): bool
    {
        if ($this->exitCode !== null) {
            return false;
        }
        return proc_get_status($this->process)['running'];
    }

    /**
     * Non-blocking: null while still running.
     * Once non-running, guarantees a *real* 0-255 exit-code (never –1).
     * @return int<0,255>|null
     */
    public function getExitCode(): ?int
    {
        if ($this->exitCode !== null) {
            return $this->exitCode;
        }

        $status = proc_get_status($this->process);
        if ($status['running']) {
            return null; // still alive
        }

        // First peek *may* already be –1; if so, fall through to close()
        if ($status['exitcode'] !== -1) {
            assert($status['exitcode'] >= 0 && $status['exitcode'] <= 255);
            return $this->exitCode = $status['exitcode'];
        }

        // Either sentinel or we want to be 100 % sure – reap the child.
        return $this->close();
    }

    /**
     * Blocking wait that always returns the real exit code.
     * Safe to call multiple times; subsequent calls return the cached value.
     * @return int<0,255>
     */
    public function wait(): int
    {
        if ($this->exitCode !== null) {
            return $this->exitCode;
        }
        // Flush & close pipes before waiting; avoids deadlocks on full buffers.
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        return $this->close();
    }

    /**
     * Send a signal (default SIGKILL) and reap the child.
     * Returns the exit code *after* the signal.
     * @return int<0,255>
     */
    public function kill(int $signal = SIGKILL): int
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process, $signal);
        }
        return $this->wait();
    }

    /**
     * Internal: closes the proc handle exactly once and caches exit code.
     * @return int<0,255>
     */
    private function close(): int
    {
        if ($this->closed) {
            return $this->exitCode ?? 250; // should already be set
        }
        $this->closed = true;
        $exitCode = proc_close($this->process);
        assert($exitCode >= 0 && $exitCode <= 255, 'Exit code must be in range 0-255');
        return $this->exitCode = $exitCode;
    }

    public function __destruct()
    {
        if (!$this->closed) {
            // kills & reaps if still running; harmless if already waited.
            $this->kill();
        }
    }
}
