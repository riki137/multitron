<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Console\TableRenderer;
use Multitron\Message\TaskProgress;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class TableOutput implements ProgressOutput
{
    private const GB = 1073741824;

    private readonly OutputInterface $section;

    /** @var list<string> */
    private array $logBuffer = [];

    private readonly TableRenderer $renderer;

    /** @var array<string, TaskState> */
    private array $states = [];

    public function __construct(
        private readonly OutputInterface $output,
        TaskList $taskList,
        private readonly bool $interactive = true
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
     * @return list<string>
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
        $freeMem = self::freeMemory();
        if ($freeMem !== null && $freeMem < self::GB) {
            $sectionBuffer[] =
                $this->renderer->getRowLabel('LOW MEMORY', TaskStatus::SKIP) .
                ' Only ' . TaskProgress::formatMemoryUsage($freeMem) . ' RAM available, processes might crash.';
        }
        $sectionBuffer[] = $this->renderer->getSummaryRow(
            $partiallyDone,
            self::memoryUsage(),
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
            if ($this->logBuffer !== []) {
                $this->output->writeln($this->logBuffer);
                $this->logBuffer = [];
            }
        }
    }

    private static function memoryUsage(): int
    {
        $pid = getmypid();
        $out = @shell_exec('ps -o rss= -p ' . $pid);
        if (is_string($out) && trim($out) !== '') {
            return (int)trim($out) * 1024;
        }

        return memory_get_usage(true);
    }

    private static function freeMemory(): ?int
    {
        if (is_readable('/proc/meminfo')) {
            $data = file_get_contents('/proc/meminfo');
            if (preg_match('/MemAvailable:\s+(\d+)/', (string)$data, $m)) {
                return (int)$m[1] * 1024;
            }
        }

        return null;
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
}
