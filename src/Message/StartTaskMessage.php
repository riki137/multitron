<?php

declare(strict_types=1);

namespace Multitron\Message;

use PhpStreamIpc\Message\Message;

final readonly class StartTaskMessage implements Message
{
    public function __construct(public string $taskId)
    {
    }
}
