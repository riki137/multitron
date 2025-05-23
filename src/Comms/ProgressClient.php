<?php
declare(strict_types=1);

namespace Multitron\Comms;

use Multitron\Message\TaskProgress;
use PhpStreamIpc\IpcSession;

final class ProgressClient
{
    private readonly TaskProgress $progress;

    private float $lastNotified = 0.0;

    public function __construct(private readonly IpcSession $session, private readonly float $interval = 0.1)
    {
        $this->progress = new TaskProgress();
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

    public function addTotal(int $n = 1): void
    {
        $this->progress->total += $n;
        $this->flush(true);
    }

    public function setDone(int $n): void
    {
        $this->progress->done = $n;
        $this->flush();
    }

    public function addDone(int $n = 1): void
    {
        $this->progress->done += $n;
        $this->flush();
    }

    public function addOccurrence(string $key, int $count = 1): void
    {
        $this->progress->addOccurrence($key, $count);
        $this->flush();
    }

    public function setOccurrence(string $key, int $count): void
    {
        $this->progress->setOccurrence($key, $count);
        $this->flush();
    }

    public function addWarning(string $warning, int $count = 1): void
    {
        $this->progress->addWarning($warning, $count);
        $this->flush();
    }

    public function setWarning(string $warning, int $count = 1): void
    {
        $this->progress->setWarning($warning, $count);
        $this->flush();
    }
}
