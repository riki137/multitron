<?php

declare(strict_types=1);

namespace Multitron\Message;

use PhpStreamIpc\Message\Message;

final readonly class StartTaskMessage implements Message
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(public string $commandName, public string $taskId, public array $options = [])
    {
    }
}
