<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler;

use Multitron\Message\TaskProgress;
use Multitron\Message\TaskWarningStateMessage;
use Multitron\Orchestrator\TaskState;
use PhpStreamIpc\Message\Message;

final class ProgressServer
{
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
