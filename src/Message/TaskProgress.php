<?php

declare(strict_types=1);

namespace Multitron\Message;

use PhpStreamIpc\Message\Message;

final class TaskProgress implements Message
{
    public int $total = 0;

    public int $done = 0;

    /** @var array<string, int> */
    public array $occurrences = [];

    /** @var array<string, int> */
    public array $warnings = [];

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

    public function addWarning(string $warning, int $count = 1): void
    {
        $this->warnings[$warning] = ($this->warnings[$warning] ?? 0) + $count;
    }

    public function setWarning(string $warning, int $count = 1): void
    {
        if ($count === 0) {
            unset($this->warnings[$warning]);
            return;
        }

        $this->warnings[$warning] = $count;
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
        $this->warnings = $progress->warnings;
    }
}
