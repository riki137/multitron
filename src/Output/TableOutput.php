<?php

declare(strict_types=1);

namespace Multitron\Output;

use Multitron\Comms\Data\Message\LogLevel;
use Multitron\Comms\Data\Message\LogMessage;
use Multitron\Comms\Data\Message\TaskProgress;
use Multitron\Process\TaskRunner;
use Multitron\Util\Throttle;
use Revolt\EventLoop;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Tracy\Debugger;
use function Amp\async;

/**
 * Handles the rendering and updating of a table displaying the progress of tasks.
 */
class TableOutput
{
    private BufferedOutput $tableOutput;

    private BufferedOutput $logOutput;

    private Table $table;

    private ConsoleSectionOutput $consoleSection;

    private Throttle $throttle;

    private array $runningTasks = [];

    private array $finishedTasks = [];

    private array $taskStartTimes = [];

    private int $taskWidth = 16;

    private int $total = 0;

    private array $start = [];

    public function __construct(TaskRunner $runner, private readonly ConsoleOutputInterface $output)
    {
        $this->tableOutput = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $this->logOutput = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $this->consoleSection = $this->output->section();

        $this->table = new Table($this->tableOutput);
        $this->table->setStyle('compact');
        foreach ($runner->getNodes() as $node) {
            $this->taskWidth = max(strlen($node->getId()), $this->taskWidth);
            $this->total++;
        }

        $this->start[''] = microtime(true);
        $this->throttle = new Throttle(fn() => $this->rewriteTable(), 100);

        async(fn() => $this->start($runner));
    }

    /**
     * Renders the table displaying the tasks' progress.
     *
     * @param array|null $tasks
     * @return string
     */
    public function renderTable(?array $tasks = null): string
    {
        $this->table->setRows([]);

        foreach ($tasks ?? $this->runningTasks as $taskId => $runningTask) {
            $progress = $runningTask->getCentre()->getProgress();
            $label = str_pad($taskId, $this->taskWidth, ' ', STR_PAD_LEFT);

            if ($progress->total > 0 && $progress->done >= $progress->total) {
                $label .= ' <fg=green>✔</>';
            } else {
                $label .= '  ';
            }

            $this->table->addRow([
                "<options=bold>$label</>",
                $this->getStatus($taskId, $progress),
            ]);
        }
        if ($tasks === null) {
            $finished = count($this->finishedTasks);
            foreach ($this->runningTasks as $runningTask) {
                $prog = $runningTask->getCentre()->getProgress();
                if ($prog->total > 0) {
                    $finished += max(0, min(1, $prog->done / $prog->total));
                }
            }
            $this->table->addRow([
                new TableCell('TOTAL  ', ['style' => new TableCellStyle(['align' => 'right', 'options' => 'bold'])]),
                ProgressBar::render($finished / $this->total * 100, 16, 'blue')
                . ' ' . count($this->finishedTasks) . '<fg=gray>/</>' . $this->total
                . ' <fg=gray>' . number_format(microtime(true) - $this->start[''], 1, '.', ' ') . 's</>',
            ]);
        }

        $this->table->render();
        return trim($this->tableOutput->fetch(), "\n\r\t");
    }

    /**
     * Rewrites the table in the console output.
     */
    private function rewriteTable(): void
    {
        $table = $this->renderTable();

        $this->logOutput->write(ob_get_clean() ?: []);
        $this->consoleSection->clear();
        $this->output->write($this->logOutput->fetch());
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
        EventLoop::setErrorHandler(function (Throwable $t) {
            $this->logOutput->writeln("ERRL: <fg=red>{$t->getMessage()}</>");
            $this->logOutput->writeln('Logged in ' . Debugger::log($t, 'EventLoop'));
            return true;
        });

        $this->rewriteTable();

        foreach ($runner->getProcesses() as $taskId => $runningTask) {
            $this->runningTasks[$taskId] = $runningTask;
            $this->taskStartTimes[$taskId] = microtime(true);
            $runningTask->getFuture()->catch(function (Throwable $t) use ($runningTask, $taskId) {
                $runningTask->getCentre()->getProgress()->error++;
                $this->logTask($taskId, $t->getMessage(), LogLevel::ERROR);
            });
            async(function () use ($taskId, $runningTask) {
                foreach ($runningTask->getCentre()->getPipeline() as $message) {
                    if ($message instanceof LogMessage) {
                        $this->logTask($taskId, $message->status, $message->level);
                    }
                    $this->throttle->call();
                }

                $this->logTask(null, $this->renderTable([$taskId => $runningTask]), LogLevel::INFO);
                unset($this->runningTasks[$taskId]);
                $this->finishedTasks[$taskId] = $runningTask;
            });
        }

        $this->rewriteTable();
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
        $message = str_replace("\n", "\n" . str_repeat(' ', 18), $message);
        if ($taskId !== null) {
            $message = "<fg={$level->toColor()};options=bold>$taskId</>: $message";
        }

        $this->logOutput->writeln($message);
        $this->throttle->call();
    }

    /**
     * Returns the status string for a task.
     *
     * @param string $taskId
     * @param TaskProgress $progress
     * @return string
     */
    private function getStatus(string $taskId, TaskProgress $progress): string
    {
        $status = '';
        $color = 'green';
        if ($progress->skipped > 0) {
            $status .= " <fg=yellow>{$progress->skipped}xSKIP</>";
            $color = 'yellow';
        }
        if ($progress->warning > 0) {
            $status .= " <fg=yellow>{$progress->warning}xWARN</>";
            $color = 'yellow';
        }
        if ($progress->done > $progress->total) {
            $color = 'yellow';
        }
        $percent = $progress->getPercentage();
        if ($progress->error > 0) {
            $status .= " <fg=red>{$progress->error}xERR</>";
            $color = 'red';
            if ($percent === 0.0) {
                $percent = 100.0;
            }
        }
        $status = ProgressBar::render(
            $percent,
            16,
            $color
        ) . ' ' . $progress->done . '<fg=gray>/</>' . $progress->total . $status;

        $status .= ' <fg=gray>' . number_format(microtime(true) - $this->taskStartTimes[$taskId], 1, '.', ' ') . 's</>';
        if ($progress->memoryUsage !== null) {
            $status .= ' <fg=blue>' . $progress->getMemoryUsage() . '</>';
        }
        return $status;
    }
}
