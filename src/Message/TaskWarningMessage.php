<?php

declare(strict_types=1);

namespace Multitron\Message;

use PhpStreamIpc\Message\Message;

final readonly class TaskWarningMessage implements Message
{
    public function __construct(
        public string $warning,
        public int $count,
        public bool $add
    ) {
    }
}
