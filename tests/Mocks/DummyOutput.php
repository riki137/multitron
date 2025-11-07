<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use Multitron\Orchestrator\Output\ProgressOutput;
use Multitron\Orchestrator\TaskState;

class DummyOutput implements ProgressOutput
{
    public array $completed = [];
    public array $logs = [];

    public function onTaskStarted(TaskState $state): void {}

    public function onTaskUpdated(TaskState $state): void {}

    public function onTaskCompleted(TaskState $state): void
    {
        $this->completed[] = $state;
    }

    public function log(TaskState $state, string $message): void
    {
        $this->logs[] = [$state, $message];
    }

    public function render(): void {}
}
