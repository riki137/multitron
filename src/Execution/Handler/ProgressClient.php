<?php
declare(strict_types=1);

namespace Multitron\Execution\Handler;

use PhpStreamIpc\IpcSession;
use Multitron\Message\TaskProgress;

final class ProgressClient
{
    private readonly TaskProgress $progress;
    private float $lastNotified = 0.0;

    public function __construct(private readonly IpcSession $session, private readonly float $interval = 0.1)
    {
        $this->progress = new TaskProgress(0);
        // TODO send warnings as text and count them
    }

    public function flush(bool $force = false): void
    {
        if ($force || (microtime(true) - $this->lastNotified) >= $this->interval) {
            $this->lastNotified = microtime(true);
            $this->session->notify($this->progress);
        }
    }

    public function setTotal(int $n): void
    {
        $this->progress->total = $n;
        $this->flush(true);
    }

    public function setDone(int $n): void
    {
        $this->progress->done = $n;
        $this->flush();
    }

    public function addDone(int $n): void
    {
        $this->progress->done += $n;
        $this->flush();
    }
}
