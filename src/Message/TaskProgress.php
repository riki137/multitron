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

    /**
     * Current completion expressed as a percentage in the range 0–100.
     * When `total` is zero the value is defined as `0`.
     */
    public function getPercentage(): float
    {
        return $this->total === 0 ? 0 : $this->toFloat() * 100;
    }

    /**
     * Completion ratio as a float between 0 and 1. A return value of `0`
     * indicates that no progress has been made yet.
     */
    public function toFloat(): float
    {
        return $this->total === 0 ? 0 : fdiv($this->done, $this->total);
    }

    /**
     * Increase the occurrence counter for a given key. Used to track
     * additional metrics such as warnings or processed files.
     */
    public function addOccurrence(string $key, int $count = 1): void
    {
        $key = $this->occurrenceKey($key);
        $this->occurrences[$key] = ($this->occurrences[$key] ?? 0) + $count;
    }

    /**
     * Overwrite the occurrence count for a key. Passing `0` removes the key
     * from the internal list.
     */
    public function setOccurrence(string $key, int $count): void
    {
        $key = $this->occurrenceKey($key);

        if ($count === 0) {
            unset($this->occurrences[$key]);
            return;
        }

        $this->occurrences[$key] = $count;
    }

    /**
     * Helper to normalize an occurrence identifier. Keys are limited to four
     * uppercase characters to keep message size small.
     */
    private function occurrenceKey(string $key): string
    {
        return strtoupper(substr($key, 0, 4));
    }

    /**
     * Merge another progress snapshot into this instance, replacing all
     * counters and memory usage information.
     */
    public function inherit(TaskProgress $progress): void
    {
        $this->total = $progress->total;
        $this->done = $progress->done;
        $this->occurrences = $progress->occurrences;
        $this->memoryUsage = $progress->memoryUsage;
    }

    /**
     * Convert a byte count into a human readable string using MB or GB units.
     */
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
