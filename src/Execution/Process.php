<?php
declare(strict_types=1);

namespace Multitron\Execution;

use RuntimeException;

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

    /** @var int<0,255>|null */
    private ?int $exitCode = null;

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
     * Once non-running, guarantees a *real* 0–255 exit code (never –1).
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

        // Happy path: PHP already exposes the real exit code.
        if ($status['exitcode'] !== -1) {
            assert($status['exitcode'] >= 0 && $status['exitcode'] <= 255);
            return $this->exitCode = (int)$status['exitcode'];
        }

        // Finished, but PHP still shows the sentinel –1.
        // Fall back to the blocking path which closes pipes first and reaps reliably.
        return $this->wait();
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

        // Close pipes before reaping to avoid buffer deadlocks and –1 from proc_close().
        foreach ($this->pipes as $i => $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
            unset($this->pipes[$i]);
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
     * Never returns –1; falls back to pre-close status or 128+signal.
     * Idempotent via $exitCode guard.
     * @return int<0,255>
     */
    private function close(): int
    {
        if ($this->exitCode !== null) {
            return $this->exitCode;
        }

        // Snapshot before closing: used if proc_close() returns –1.
        $st = proc_get_status($this->process);
        $preExit = (!$st['running'] && $st['exitcode'] !== -1) ? (int)$st['exitcode'] : null;
        $termSig = (!empty($st['signaled']) && isset($st['termsig'])) ? (int)$st['termsig'] : null;

        $rc = proc_close($this->process);

        if ($rc === -1) {
            if ($preExit !== null) {
                $rc = $preExit; // trusted real exit captured earlier
            } elseif ($termSig !== null && $termSig > 0) {
                // POSIX convention: 128 + signal (e.g. SIGKILL => 137)
                $rc = 128 + $termSig;
            } else {
                // Last-resort valid code; loud in logs, never negative.
                $rc = 255;
            }
        }

        assert($rc >= 0 && $rc <= 255, 'Exit code must be in range 0–255');
        return $this->exitCode = $rc;
    }

    public function __destruct()
    {
        // Kills & reaps if still running; harmless if already waited.
        if ($this->exitCode === null) {
            $this->kill();
        }
    }
}
