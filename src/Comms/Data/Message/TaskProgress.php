<?php
declare(strict_types=1);

namespace Multitron\Comms\Data\Message;

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
        return $this->memoryUsage === null ? null : self::formatMemoryUsage($this->memoryUsage);
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

    public static function formatMemoryUsage(int $memoryUsage): string
    {
        $memoryUsage /= 1024 * 1024;
        if ($memoryUsage >= 1000) {
            return number_format($memoryUsage / 1024, 1, '.', '') . 'GB';
        }
        if ($memoryUsage >= 10) {
            return number_format($memoryUsage, 0, '.', '') . 'MB';
        }
        return number_format($memoryUsage, 1, '.', '') . 'MB';
    }

    public function toFloat(): float
    {
        return fdiv($this->done + $this->skipped, $this->total);
    }
}
