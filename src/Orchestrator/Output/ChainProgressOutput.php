<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Orchestrator\TaskState;

final class ChainProgressOutput implements ProgressOutput
{
    /** @var ProgressOutput[] */
    private array $outputs = [];

    public function __construct(ProgressOutput ...$outputs)
    {
        $this->outputs = $outputs;
    }

    public function onTaskStarted(TaskState $state): void
    {
        foreach ($this->outputs as $output) {
            $output->onTaskStarted($state);
        }
    }

    public function onTaskUpdated(TaskState $state): void
    {
        foreach ($this->outputs as $output) {
            $output->onTaskUpdated($state);
        }
    }

    public function onTaskCompleted(TaskState $state): void
    {
        foreach ($this->outputs as $output) {
            $output->onTaskCompleted($state);
        }
    }

    public function log(TaskState $state, string $message): void
    {
        foreach ($this->outputs as $output) {
            $output->log($state, $message);
        }
    }

    public function render(): void
    {
        foreach ($this->outputs as $output) {
            $output->render();
        }
    }
}
