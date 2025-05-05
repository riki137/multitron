<?php

declare(strict_types=1);

namespace Multitron\Message;

use PhpStreamIpc\Message\Message;

final class TaskProgress implements Message
{
    public function __construct(
        public int $total = 0,
        public int $done = 0,
        public int $error = 0,
        public int $warning = 0,
        public int $skipped = 0,
    )
    {
    }
}
