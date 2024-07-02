<?php

declare(strict_types=1);

namespace Multitron\Output;

use Amp\Process\Process;
use Multitron\Comms\Data\Message\LogLevel;
use Multitron\Comms\Data\Message\LogMessage;
use Multitron\Output\Table\ProgressBar;
use Multitron\Output\Table\TaskTable;
use Multitron\Process\RunningTask;
use Multitron\Process\TaskRunner;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function Amp\async;
use function Amp\delay;

/**
 * Handles the rendering and updating of a table displaying the progress of tasks.
 */
final class TableOutput
{
    private BufferedOutput $logOutput;

    private TaskTable $table;

    private ConsoleSectionOutput $consoleSection;

    private array $runningTasks = [];

    /** @var RunningTask[] */
    private array $finishedTasks = [];

    private int $taskWidth = 16;

    private int $total = 0;

    private array $start = [];

    private array $errorCount = [];

    private int $maxMem = 0;

    private bool $running = true;

    public function __construct(TaskRunner $runner, private readonly ConsoleOutputInterface $output)
    {
        $this->logOutput = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $this->consoleSection = $this->output->section();
        $this->table = new TaskTable();

        foreach ($runner->getNodes() as $node) {
            $this->table->taskWidth = max(strlen($node->getId()), $this->taskWidth);
            $this->total++;
        }

        async(fn() => $this->start($runner));
        async(function () {
            while ($this->running) {
                delay(0.1);
                $this->rewriteTable();
            }
        });
        async(function () {
            $this->monitorMemory();
        });
    }

    private function monitorMemory(): void
    {
        while ($this->running) {
            foreach (Process::start('free -b')->getStdout() as $line) {
                preg_match('/Mem:\s+(\d+)\s+(\d+)\s+/', $line, $matches);
                [, $total, $used] = $matches;
                $this->table->ramTotal = (int)$total;
                $this->table->ramUsed = (int)$used;
            }
            delay(1);
        }
    }

    /**
     * Renders the table displaying the tasks' progress.
     */
    public function renderTable(): string
    {
        $table = [];

        $totalMem = 0;
        /** @var RunningTask $runningTask */
        foreach ($this->runningTasks as $taskId => $runningTask) {
            $progress = $runningTask->getCentre()->getProgress();
            $totalMem += $progress->memoryUsage ?? 0;
            $table[] = $this->table->getRow($taskId, $progress);
        }

        $finished = count($this->finishedTasks);
        foreach ($this->runningTasks as $runningTask) {
            $prog = $runningTask->getCentre()->getProgress();
            if ($prog->total > 0) {
                $finished += max(0, min(1, $prog->toFloat()));
            }
        }
        $totalMem += memory_get_usage(true);
        $this->maxMem = max($this->maxMem, $totalMem);
        $table[] = '';
        $table[] = $this->table->getSummaryRow($finished, $this->total);
        $table[] = $this->table->getMemoryRow($this->maxMem);

        return trim(implode("\n", $table), "\n\r\t");
    }

    /**
     * Rewrites the table in the console output.
     */
    private function rewriteTable(): void
    {
        $table = $this->renderTable();

        $this->logOutput->write(ob_get_clean() ?: []);
        $this->consoleSection->clear();
        $output = $this->logOutput->fetch();
        if ($output !== '') {
            $this->output->writeln(trim($output, "\n"));
        }
        $this->consoleSection->write("\n" . $table);
        ob_start();
    }

    /**
     * Starts the process of tracking and displaying the tasks' progress.
     *
     * @param TaskRunner $runner
     */
    public function start(TaskRunner $runner): void
    {
        $this->rewriteTable();

        foreach ($runner->getProcesses() as $taskId => $runningTask) {
            $this->runningTasks[$taskId] = $runningTask;
            $this->table->startTimes[$taskId] = microtime(true);
            $progress = $runningTask->getCentre()->getProgress();
            $runningTask->getFuture()->catch(function (Throwable $t) use ($progress, $taskId) {
                $progress->error++;
                $this->logTask($taskId, $t->getMessage(), LogLevel::ERROR);
            });
            async(function () use ($progress, $taskId, $runningTask) {
                try {
                    foreach ($runningTask->getCentre()->getPipeline() as $message) {
                        if ($message instanceof LogMessage) {
                            $this->logTask($taskId, $message->message, $message->level);
                            if (in_array(
                                $message->level,
                                [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::EMERGENCY, LogLevel::ALERT],
                                true
                            )) {
                                $this->errorCount[$taskId] = ($this->errorCount[$taskId] ?? 0) + 1;
                            }
                        }
                    }
                    try {
                        $exitCode = $runningTask->getFuture()->await();
                    } catch (Throwable $e) {
                        $this->logTask($taskId, $e->getMessage(), LogLevel::ERROR);
                        $exitCode = 1;
                    }
                    if ($exitCode !== 0) {
                        $runningTask->getCentre()->getProgress()->error++;
                    }
                    $this->logTask(null, $this->table->getRow($taskId, $progress), LogLevel::INFO);
                    unset($this->runningTasks[$taskId]);
                    $this->finishedTasks[$taskId] = $runningTask;
                } catch (Throwable $t) {
                    $this->logTask($taskId, $t->getMessage(), LogLevel::ERROR);
                }
            });
        }
        $this->rewriteTable();
        $this->running = false;
    }

    /**
     * Logs a task's status.
     *
     * @param string|null $taskId
     * @param string $message
     * @param LogLevel $level
     */
    public function logTask(?string $taskId, string $message, LogLevel $level): void
    {
        $message = str_replace("\n", "\n" . str_repeat(' ', $this->taskWidth + 3), $message);
        if ($taskId !== null) {
            $message = "<fg={$level->toColor()};options=bold>$taskId</>: $message";
        }

        $this->logOutput->writeln($message);
    }
}
