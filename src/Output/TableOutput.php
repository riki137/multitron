<?php

declare(strict_types=1);

namespace Multitron\Output;

use Amp\Cancellation;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\Process\Process;
use Multitron\Comms\Data\Message\LogLevel;
use Multitron\Comms\Data\Message\LogMessage;
use Multitron\Console\InputConfiguration;
use Multitron\Output\Table\TaskTable;
use Multitron\Process\RunningTask;
use Multitron\Process\TaskRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function Amp\async;
use function Amp\delay;
use function Amp\Future\awaitAll;

/**
 * Handles the rendering and updating of a table displaying the progress of tasks.
 */
final class TableOutput
{
    private TaskTable $table;

    private array $runningTasks = [];

    /** @var RunningTask[] */
    private array $finishedTasks = [];

    private ?array $summaryLog = null;

    private OutputInterface $output;

    public function configure(InputConfiguration $conf): void
    {
        $conf->addOption('redraw-interval', null, InputOption::VALUE_REQUIRED, 'The interval in seconds between table redraws', 0.1);
        $conf->addOption('decorate', null, InputOption::VALUE_NEGATABLE, 'Enable or disable output decoration');
        $conf->addOption('no-progress', null, InputOption::VALUE_NONE, 'Animate progress bars');
        $conf->addOption('summary', null, InputOption::VALUE_NONE, 'Show a summary of the task list after completion');
    }

    public function run(TaskRunner $runner, InputInterface $input, ConsoleOutputInterface $output): Future
    {
        $this->summaryLog = $input->getOption('summary') ? [] : null;
        $this->table = new TaskTable($runner);

        $cancel = new DeferredCancellation();
        return async(fn() => awaitAll([
            $this->renderProgress($input, $output, $cancel->getCancellation()),
            async(fn() => $this->start($runner, $cancel)),
            async(fn() => $this->monitorMemory($cancel->getCancellation())),
        ]));
    }

    private function renderProgress(InputInterface $input, ConsoleOutputInterface $output, Cancellation $cancel): Future
    {
        $output->setDecorated($input->getOption('decorate') ?? $output->isDecorated());

        if ($input->getOption('no-progress') || !$output->isDecorated()) {
            $this->output = $output;
            return Future::complete();
        }

        $this->output = new CombinedSectionOutputRenderer(
            $output,
            $this->rewriteTable(...),
            (float)$input->getOption('redraw-interval')
        );
        return async(fn() => $this->output->render($cancel));
    }

    private function start(TaskRunner $runner, DeferredCancellation $cancel): void
    {
        foreach ($runner->getProcesses() as $taskId => $runningTask) {
            $this->runningTasks[$taskId] = $runningTask;
            $this->table->startTimes[$taskId] = microtime(true);

            $progress = $runningTask->getCentre()->getProgress();
            $runningTask->getFuture()->catch(function (Throwable $t) use ($progress, $taskId) {
                $progress->error++;
                $this->log($taskId, $t->getMessage(), LogLevel::ERROR);
            });

            async(function () use ($progress, $taskId, $runningTask) {
                try {
                    foreach ($runningTask->getCentre()->getPipeline() as $message) {
                        if ($message instanceof LogMessage) {
                            $this->log($taskId, $message->message, $message->level);
                        }
                    }

                    try {
                        $exitCode = $runningTask->getFuture()->await();
                    } catch (Throwable $e) {
                        $this->log($taskId, $e->getMessage(), LogLevel::ERROR);
                        $exitCode = 1;
                    }

                    $this->log($taskId, $this->table->getRow($taskId, $progress, $exitCode), LogLevel::INFO, false);
                    unset($this->runningTasks[$taskId]);
                    $this->finishedTasks[$taskId] = $runningTask;
                } catch (Throwable $t) {
                    $this->log($taskId, $t->getMessage(), LogLevel::ERROR);
                }
            });
        }
        $cancel->cancel();

        if ($this->summaryLog !== null) {
            $this->printSummary();
        }
    }

    private function monitorMemory(Cancellation $cancel): void
    {
        while (!$cancel->isRequested()) {
            foreach (Process::start('free -b')->getStdout() as $line) {
                if (preg_match('/Mem:\s+(\d+)\s+(\d+)\s+/', $line, $matches)) {
                    [, $total, $used] = $matches;
                    $this->table->ramTotal = (int)$total;
                    $this->table->ramUsed = (int)$used;
                }
            }
            delay(1);
        }
    }

    private function rewriteTable(): string
    {
        $table = [];
        $totalMem = 0;

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

        $table[] = '';
        $table[] = $this->table->getSummaryRow($finished);
        $table[] = $this->table->getMemoryRow($totalMem);

        return PHP_EOL . trim(implode(PHP_EOL, $table), "\n\r\t");
    }

    private function log(string $taskId, string $message, LogLevel $level, bool $prepend = true): void
    {
        $formatted = $this->table->getLog($prepend ? $taskId : null, $message, $level);
        $this->output->writeln($formatted);
        if ($this->summaryLog !== null) {
            $this->summaryLog[$taskId][] = $formatted;
        }
    }

    private function printSummary(): void
    {
        $this->output->writeln("\n<fg=magenta;options=bold>" .
            "┌───────────┐\n" .
            "│  SUMMARY  │\n" .
            '└───────────┘</>');

        foreach ($this->finishedTasks as $taskId => $runningTask) {
            if (is_array($this->summaryLog[$taskId] ?? null)) {
                $this->output->writeln(implode(PHP_EOL, $this->summaryLog[$taskId]));
                unset($this->summaryLog[$taskId]);
            }
        }

        foreach ($this->summaryLog ?? [] as $log) {
            $this->output->writeln(implode(PHP_EOL, $log));
        }
        $this->output->writeln(PHP_EOL . $this->table->getSummaryRow(count($this->finishedTasks)));
        $this->output->writeln($this->table->getMemoryRow(0));
    }
}
