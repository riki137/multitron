<?php

declare(strict_types=1);

namespace Multitron\Message;

use PhpStreamIpc\Message\Message;

final class TaskProgress implements Message
{
    public int $total = 0;

    public int $done = 0;

    /** @var array<string, int> */
    public array $occurences = [];

    /** @var array<string, int> */
    public array $warnings = [];

    public function getPercentage(): float
    {
        return $this->total === 0 ? 0 : $this->toFloat() * 100;
    }

    public function toFloat(): float
    {
        return fdiv($this->done, $this->total);
    }

    public function addOccurence(string $key, int $count = 1): void
    {
        $key = $this->occurenceKey($key);
        $this->occurences[$key] = ($this->occurences[$key] ?? 0) + $count;
    }

    public function setOccurence(string $key, int $count): void
    {
        $key = $this->occurenceKey($key);

        if ($count === 0) {
            unset($this->occurences[$key]);
            return;
        }

        $this->occurences[$key] = $count;
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

    private function occurenceKey(string $key): string
    {
        return strtoupper(substr($key, 0, 4));
    }

    public function inherit(TaskProgress $progress): void
    {
        $this->total = $progress->total;
        $this->done = $progress->done;
        $this->occurences = $progress->occurences;
        $this->warnings = $progress->warnings;
    }
}
