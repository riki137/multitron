<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\Process\Process;

final class MemoryUsage
{
    private const UPDATE_FREQUENCY = 1;

    private int $memoryUsage = 0;

    private int|false $pid;

    private float $lastUpdate = 0;

    public function __construct()
    {
        $this->pid = getmypid();
    }

    public function getMemoryUsage(): int
    {
        if ($this->shouldUpdateMemory()) {
            $this->updateMemory();
        }
        return $this->memoryUsage;
    }

    public static function format(int $memoryUsage): string
    {
        $memoryUsageMB = $memoryUsage / 1024 / 1024;

        if ($memoryUsageMB >= 1000) {
            return number_format($memoryUsageMB / 1024, 1, '.', '') . 'GB';
        }

        return ($memoryUsageMB >= 10)
            ? number_format($memoryUsageMB, 0, '.', '') . 'MB'
            : number_format($memoryUsageMB, 1, '.', '') . 'MB';
    }

    private function updateMemory(): void
    {
        if (is_int($this->pid)) {
            $usage = $this->fetchMemoryUsage();
            $this->memoryUsage = $usage ? $usage * 1024 : memory_get_usage(true);
        } else {
            $this->memoryUsage = memory_get_usage(true);
        }
        $this->lastUpdate = microtime(true);
    }

    private function shouldUpdateMemory(): bool
    {
        return (microtime(true) - $this->lastUpdate) > self::UPDATE_FREQUENCY;
    }

    private function fetchMemoryUsage(): ?int
    {
        $proc = Process::start('ps -o rss= -p ' . $this->pid);
        return $proc->join() === 0 ? (int) trim($proc->getStdout()->read() ?? '') : null;
    }
}
