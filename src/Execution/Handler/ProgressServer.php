<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler;

use Multitron\Message\TaskProgress;
use Multitron\Message\TaskWarningStateMessage;
use Multitron\Orchestrator\TaskState;
use StreamIpc\Message\Message;

final class ProgressServer
{
    /**
     * Merge progress updates and warnings received over IPC into the provided
     * task state instance.
     */
    public function handleProgress(Message $progress, TaskState $state): void
    {
        if ($progress instanceof TaskProgress) {
            $state->getProgress()->inherit($progress);
        }
        if ($progress instanceof TaskWarningStateMessage) {
            $state->getWarnings()->fromMessage($progress);
        }
    }
}
