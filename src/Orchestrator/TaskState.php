<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Execution\Execution;
use Multitron\Message\TaskProgress;

class TaskState
{
    private TaskStatus $status = TaskStatus::RUNNING;

    private readonly float $startedAt;

    private readonly TaskProgress $progress;

    private readonly TaskWarningState $warnings;

    public function __construct(
        private readonly string $taskId,
        private readonly ?Execution $execution = null,
    ) {
        $this->startedAt = microtime(true);
        $this->progress = new TaskProgress();
        $this->warnings = new TaskWarningState();
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): void
    {
        $this->status = $status;
    }

    public function getExecution(): ?Execution
    {
        return $this->execution;
    }

    public function getStartedAt(): float
    {
        return $this->startedAt;
    }

    public function getProgress(): TaskProgress
    {
        return $this->progress;
    }

    public function getWarnings(): TaskWarningState
    {
        return $this->warnings;
    }
}
