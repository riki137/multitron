<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Orchestrator\TaskState;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

final class TableOutput
{
    private ConsoleSectionOutput $section;

    /** @var array<string, TaskState> */
    private array $states = [];

    public function __construct(ConsoleSectionOutput $section)
    {
        $this->section = $section;
    }

    /**
     * Call whenever a task is launched or polled for status.
     */
    public function updateTask(TaskState $state): void
    {
        $this->states[$state->getTaskId()] = $state;
        $this->render();
    }

    /**
     * Call whenever a task completes (success, error, or skip).
     */
    public function completeTask(TaskState $state): void
    {
        $this->states[$state->getTaskId()] = $state;
        $this->render();
    }

    /**
     * Get all current task states.
     *
     * @return TaskState[]
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * Stub for your rendering logic. Use $this->states and $this->section.
     */
    protected function render(): void
    {
        // TODO
    }
}
