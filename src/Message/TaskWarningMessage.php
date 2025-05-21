<?php

declare(strict_types=1);

namespace Multitron\Message;

use PhpStreamIpc\Message\Message;

class TaskWarningMessage implements Message
{
    public function __construct(
        public string $warning,
        public int $count = 1,
    )
    {
    }
}
