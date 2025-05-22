<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Console\TaskTable;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class TableOutput implements ProgressOutput
{
    private readonly OutputInterface $section;

    private array $logBuffer = [];

    private readonly TaskTable $table;

    /** @var array<string, TaskState> */
    private array $states = [];

    public function __construct(private readonly OutputInterface $output, TaskList $taskList)
    {
        if ($output instanceof ConsoleOutputInterface) {
            $this->section = $output->section();
        } else {
            $this->section = $output;
        }
        $this->table = new TaskTable($taskList);
    }

    public function onTaskStarted(TaskState $state): void
    {
        $this->table->markStarted($state->getTaskId());
        $this->states[$state->getTaskId()] = $state;
    }

    public function onTaskUpdated(TaskState $state): void
    {
    }

    public function onTaskCompleted(TaskState $state): void
    {
        unset($this->states[$state->getTaskId()]);
        $this->logBuffer[] = $this->table->getLog(
            null,
            $this->table->getRow($state->getTaskId(), $state->getProgress(), $state->getStatus())
        );
    }

    public function log(TaskState $state, string $message): void
    {
        $this->logBuffer[] = $this->table->getLog($state->getTaskId(), $message);
    }

    public function render(): void
    {
        $sectionBuffer = [];
        $totalDone = 0;
        // Render each task
        foreach ($this->states as $state) {
            $exitCode = match ($state->getStatus()) {
                TaskStatus::RUNNING => null,
                TaskStatus::SUCCESS => 0,
                TaskStatus::SKIP => -1,
                TaskStatus::ERROR => 1,
            };

            $sectionBuffer[] = $this->table->getRow(
                $state->getTaskId(),
                $state->getProgress(),
                $state->getStatus(),
            );
            $totalDone += $state->getProgress()->toFloat();
        }
        $sectionBuffer[] = $this->table->getSummaryRow($totalDone);
        if ($this->section instanceof ConsoleSectionOutput) {
            $this->section->clear();
        }
        $this->output->writeln($this->logBuffer);
        $this->section->writeln($sectionBuffer);
        $this->logBuffer = [];
    }
}
