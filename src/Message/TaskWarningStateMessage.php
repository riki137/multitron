<?php

declare(strict_types=1);

namespace Multitron\Message;

use StreamIpc\Message\Message;

final readonly class TaskWarningStateMessage implements Message
{
    public function __construct(
        public array $warnings,
        public array $warningCount
    ) {
    }
}
