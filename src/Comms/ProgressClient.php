<?php
declare(strict_types=1);

namespace Multitron\Comms;

use Multitron\Message\TaskProgress;
use Multitron\Orchestrator\TaskWarningState;
use StreamIpc\IpcSession;

final class ProgressClient
{
    private readonly TaskProgress $progress;

    private readonly TaskWarningState $warnings;

    private float $lastNotified = 0.0;

    /**
     * Helper for publishing {@see TaskProgress} updates and warnings.
     * The progress snapshot is kept locally and only sent every `$interval`
     * seconds to avoid excessive IPC traffic.
     *
     * @param IpcSession $session  communication channel used for notifications
     * @param float      $interval minimum seconds between progress reports
     */
    public function __construct(private readonly IpcSession $session, private readonly float $interval = 0.1)
    {
        $this->progress = new TaskProgress();
        $this->warnings = new TaskWarningState();
    }

    /**
     * Publish the currently collected progress snapshot to the session.
     * Memory usage is captured right before sending. If `$force` is false the
     * update is skipped until the configured interval has passed.
     */
    public function flush(bool $force = true): void
    {
        if ($force || ((microtime(true) - $this->lastNotified) >= $this->interval)) {
            $this->lastNotified = microtime(true);
            $this->progress->memoryUsage = self::memoryUsage();
            $this->session->notify($this->progress);
        }
    }

    /**
     * Replace the expected number of work units and immediately issue an
     * update so listeners can adjust their progress displays.
     */
    public function setTotal(int $n): void
    {
        $this->progress->total = $n;
        $this->flush();
    }

    /**
     * Increase the anticipated total work units and broadcast the new value
     * to any attached progress listeners.
     */
    public function addTotal(int $n = 1): void
    {
        $this->progress->total += $n;
        $this->flush();
    }

    /**
     * Overwrite the number of completed units. The update is throttled to
     * avoid a burst of IPC messages when tasks complete quickly.
     */
    public function setDone(int $n): void
    {
        $this->progress->done = $n;
        $this->flush(false);
    }

    /**
     * Add to the count of completed work units. The flush is not forced so
     * multiple fast updates may coalesce into a single notification.
     */
    public function addDone(int $n = 1): void
    {
        $this->progress->done += $n;
        $this->flush(false);
    }

    /**
     * Increase the occurrence counter identified by `$key`. Useful for
     * tracking metrics such as processed files or emitted warnings.
     */
    public function addOccurrence(string $key, int $count = 1): void
    {
        $this->progress->addOccurrence($key, $count);
        $this->flush(false);
    }

    /**
     * Replace the occurrence counter for `$key` with an exact value.
     * The progress update is deferred until the next flush.
     */
    public function setOccurrence(string $key, int $count): void
    {
        $this->progress->setOccurrence($key, $count);
        $this->flush(false);
    }

    /**
     * Record another occurrence of the given warning. The message itself is
     * delivered on {@see shutdown()} to keep runtime overhead low.
     */
    public function addWarning(string $warning, int $count = 1): void
    {
        $this->warnings->add($warning, $count);
    }

    /**
     * Replace the stored count for a warning type. This value will be sent
     * once the task completes.
     */
    public function setWarning(string $warning, int $count = 1): void
    {
        $this->warnings->set($warning, $count);
    }

    /**
     * Send any pending progress update and transmit all aggregated warnings.
     * Should be called when the task finishes or the process is exiting.
     */
    public function shutdown(): void
    {
        $this->flush(true);
        $this->session->notify($this->warnings->toMessage());
    }

    /**
     * Helper for retrieving the current memory footprint of the PHP process.
     * Uses the real usage flag so the number matches system allocation.
     */
    private static function memoryUsage(): int
    {
        return memory_get_usage(true);
    }
}
