<?php
declare(strict_types=1);

namespace Multitron\Comms\Data\Message;

class ProgressMessage implements Message
{
    public function __construct(
        public int $total,
        public int $done = 0,
        public int $error = 0,
        public int $warning = 0,
        public int $skipped = 0
    ) {
    }

    public static function getId(): string
    {
        return 'progress';
    }
}
