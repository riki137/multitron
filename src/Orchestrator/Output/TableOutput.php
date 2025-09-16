<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Console\TableRenderer;
use Multitron\Message\TaskProgress;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Orchestrator\System\MemoryInfo;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class TableOutput implements ProgressOutput
{
    private const MB = 1048576;

    private readonly OutputInterface $section;

    /** @var string[] */
    private array $logBuffer = [];

    private readonly TableRenderer $renderer;

    /** @var array<string, TaskState> */
    private array $states = [];

    /**
     * @internal use TableOutputFactory instead
     */
    public function __construct(
        private readonly OutputInterface $output,
        TaskList $taskList,
        private readonly bool $interactive,
        private readonly int $lowMemoryWarning,
    ) {
        if ($interactive && $output instanceof ConsoleOutputInterface) {
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
        $this->renderer->markFinished();
    }

    public function log(TaskState $state, string $message): void
    {
        $this->logBuffer[] = $this->renderer->getLog($state->getTaskId(), $message);
    }

    /**
     * @return string[]
     */
    private function buildSectionBuffer(): array
    {
        $sectionBuffer = [];
        $workersMem = 0;
        $partiallyDone = 0;
        foreach ($this->states as $state) {
            $sectionBuffer[] = $this->renderer->getRow($state);
            $partiallyDone += $state->getProgress()->toFloat();
            $mem = $state->getProgress()->memoryUsage;
            if ($mem !== null) {
                $workersMem += $mem;
            }
        }
        $this->attachMemoryWarning($sectionBuffer);
        $sectionBuffer[] = $this->renderer->getSummaryRow(
            $partiallyDone,
            MemoryInfo::processBytes(),
            $workersMem,
        );

        return $sectionBuffer;
    }

    public function render(): void
    {
        if ($this->interactive) {
            $sectionBuffer = $this->buildSectionBuffer();
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
        } else {
            $this->attachMemoryWarning($this->logBuffer);
            if ($this->logBuffer !== []) {
                $this->output->writeln($this->logBuffer);
                $this->logBuffer = [];
            }
        }
    }

    public function __destruct()
    {
        if ($this->interactive) {
            $this->render();
            $this->output->write(ob_get_clean() ?: '');
        } else {
            $section = $this->buildSectionBuffer();
            $this->output->writeln(array_merge($this->logBuffer, $section));
        }
    }

    /**
     * @param string[] $buffer
     * @return void
     */
    private function attachMemoryWarning(array &$buffer): void
    {
        if ($this->lowMemoryWarning < 1) {
            return;
        }

        $freeMem = MemoryInfo::availableBytes();

        if ($freeMem !== null && $freeMem < ($this->lowMemoryWarning * self::MB)) {
            $buffer[] =
                $this->renderer->getRowLabel('LOW MEMORY', TaskStatus::SKIP) .
                ' Only ' . TaskProgress::formatMemoryUsage($freeMem) . ' RAM available, processes might crash.';
        }
    }
}
