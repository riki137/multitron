<?php
declare(strict_types=1);

namespace Multitron\Comms\Data\Message;

use Multitron\Process\MemoryUsage;

final class TaskProgress implements Message
{
    public function __construct(
        public int $total,
        public int $done = 0,
        public int $error = 0,
        public int $warning = 0,
        public int $skipped = 0,
        public ?int $memoryUsage = null,
    ) {
    }

    public function getPercentage(): float
    {
        return $this->total === 0 ? 0 : $this->toFloat() * 100;
    }

    public function getMemoryUsage(): ?string
    {
        return $this->memoryUsage === null ? null : MemoryUsage::format($this->memoryUsage);
    }

    public function update(TaskProgress $progress): void
    {
        $this->total = $progress->total;
        $this->done = $progress->done;
        $this->error = $progress->error;
        $this->warning = $progress->warning;
        $this->skipped = $progress->skipped;
        $this->memoryUsage = $progress->memoryUsage;
    }

    public function toFloat(): float
    {
        return fdiv($this->done + $this->skipped, $this->total);
    }
}
