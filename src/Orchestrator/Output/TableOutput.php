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
        $workersMem = 0;
        // Render each task
        foreach ($this->states as $state) {
            $sectionBuffer[] = $this->renderer->getRow($state);
            $totalDone += $state->getProgress()->toFloat();
            $mem = $state->getProgress()->memoryUsage;
            if ($mem !== null) {
                $workersMem += $mem;
            }
        }
        $sectionBuffer[] = $this->renderer->getSummaryRow(
            $totalDone,
            self::memoryUsage(),
            $workersMem,
            self::freeMemory(),
        );
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
        $this->render();
        echo ob_get_clean();
    }
}
