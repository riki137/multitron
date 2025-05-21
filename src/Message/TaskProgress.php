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

    private function occurenceKey(string $key): string
    {
        return strtoupper(substr($key, 0, 4));
    }

    public function inherit(TaskProgress $progress): void
    {
        $this->total = $progress->total;
        $this->done = $progress->done;
        $this->occurences = $progress->occurences;
    }
}
