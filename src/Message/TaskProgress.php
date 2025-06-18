<?php

declare(strict_types=1);

namespace Multitron\Message;

use StreamIpc\Message\Message;

final class TaskProgress implements Message
{
    public int $total = 0;

    public int $done = 0;

    /** @var array<string, int> */
    public array $occurrences = [];

    public ?int $memoryUsage = null;

    public function getPercentage(): float
    {
        return $this->total === 0 ? 0 : $this->toFloat() * 100;
    }

    public function toFloat(): float
    {
        return $this->total === 0 ? 0 : fdiv($this->done, $this->total);
    }

    public function addOccurrence(string $key, int $count = 1): void
    {
        $key = $this->occurrenceKey($key);
        $this->occurrences[$key] = ($this->occurrences[$key] ?? 0) + $count;
    }

    public function setOccurrence(string $key, int $count): void
    {
        $key = $this->occurrenceKey($key);

        if ($count === 0) {
            unset($this->occurrences[$key]);
            return;
        }

        $this->occurrences[$key] = $count;
    }

    private function occurrenceKey(string $key): string
    {
        return strtoupper(substr($key, 0, 4));
    }

    public function inherit(TaskProgress $progress): void
    {
        $this->total = $progress->total;
        $this->done = $progress->done;
        $this->occurrences = $progress->occurrences;
        $this->memoryUsage = $progress->memoryUsage;
    }

    public static function formatMemoryUsage(int $bytes): string
    {
        $m = $bytes / 1048576;
        if ($m >= 1024) {
            return number_format($m / 1024, 1, '.', '') . 'GB';
        }
        if ($m >= 10) {
            return number_format($m, 0, '.', '') . 'MB';
        }
        return number_format($m, 1, '.', '') . 'MB';
    }
}
