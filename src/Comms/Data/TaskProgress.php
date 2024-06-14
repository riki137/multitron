<?php
declare(strict_types=1);

namespace Multitron\Comms\Data;

use Multitron\Comms\Data\Message\ProgressMessage;

final class TaskProgress
{
    public function __construct(
        public int $total = 0,
        public int $done = 0,
        public int $error = 0,
        public int $warning = 0,
        public int $skipped = 0
    ) {
    }

    public function getPercentage(): float
    {
        return $this->total === 0 ? 0 : ($this->done / $this->total) * 100;
    }

    public function update(ProgressMessage $message): void
    {
        $this->total = $message->total;
        $this->done = $message->done;
        $this->error = $message->error;
        $this->warning = $message->warning;
        $this->skipped = $message->skipped;
    }
}
