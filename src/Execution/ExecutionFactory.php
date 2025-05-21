<?php

declare(strict_types=1);

namespace Multitron\Execution;

interface ExecutionFactory
{
    public function launch(string $commandName, string $taskId, array $options): Execution;

    public function shutdown(): void;
}
