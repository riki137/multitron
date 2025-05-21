<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler;

use Multitron\Message\TaskProgress;
use Multitron\Orchestrator\TaskState;

final class ProgressServer
{
    public function handleProgress(TaskProgress $progress, TaskState $state): void
    {
        $state->getProgress()->inherit($progress);
    }
}
