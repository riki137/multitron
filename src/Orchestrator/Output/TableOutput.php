<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Console\TableRenderer;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class TableOutput implements ProgressOutput
{
    private readonly OutputInterface $section;

    /** @var list<string> */
    private array $logBuffer = [];

    private readonly TableRenderer $renderer;

    /** @var array<string, TaskState> */
    private array $states = [];

    public function __construct(private readonly OutputInterface $output, TaskList $taskList)
    {
        if ($output instanceof ConsoleOutputInterface) {
            $this->section = $output->section();
        } else {
            $this->section = $output;
        }
        $this->renderer = new TableRenderer($taskList);
    }

    public function onTaskStarted(TaskState $state): void
    {
        $this->states[$state->getTaskId()] = $state;
    }

    public function onTaskUpdated(TaskState $state): void
    {
    }

    public function onTaskCompleted(TaskState $state): void
    {
        unset($this->states[$state->getTaskId()]);
        $this->logBuffer[] = $this->renderer->getLog(
            null,
            $this->renderer->getRow($state)
        );
        foreach ($state->getWarnings()->fetchAll() as $warning) {
            $this->logBuffer[] = $this->renderer->renderWarning($state->getTaskId(), $warning);
        }
        $this->renderer->markFinished($state->getTaskId());
    }

    public function log(TaskState $state, string $message): void
    {
        $this->logBuffer[] = $this->renderer->getLog($state->getTaskId(), $message);
    }

    public function render(): void
    {
        $sectionBuffer = [];
        $totalDone = 0;
        // Render each task
        foreach ($this->states as $state) {
            $sectionBuffer[] = $this->renderer->getRow($state);
            $totalDone += $state->getProgress()->toFloat();
        }
        $sectionBuffer[] = $this->renderer->getSummaryRow($totalDone);
        $ob = '';
        if (ob_get_level() > 0) {
            $ob = ob_get_clean();
        }
        if (is_string($ob) && trim($ob) !== '') {
            $this->logBuffer[] = trim($ob);
        }

        if ($this->section instanceof ConsoleSectionOutput) {
            $this->section->clear();
        }
        $this->output->writeln($this->logBuffer);
        $this->section->writeln($sectionBuffer);
        $this->logBuffer = [];

        ob_start();
    }

    public function __destruct()
    {
        $this->render();
        echo ob_get_clean();
    }
}
