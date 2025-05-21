<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Orchestrator\TaskState;
use Symfony\Component\Console\Output\OutputInterface;

interface ProgressOutput
{
    public function onTaskStarted(TaskState $state): void;

    public function onTaskUpdated(TaskState $state): void;

    public function onTaskCompleted(TaskState $state): void;

    public function log(TaskState $state, string $message): void;

    public function render(): void;
}
