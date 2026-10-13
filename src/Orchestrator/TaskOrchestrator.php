<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Execution\CpuDetector;
use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use Multitron\Orchestrator\Output\ProgressOutput;
use Multitron\Orchestrator\Output\ProgressOutputFactory;
use Multitron\Tree\CompiledTaskNode;
use Multitron\Tree\TaskTreeQueue;
use RuntimeException;
use StreamIpc\InvalidStreamException;
use StreamIpc\IpcPeer;
use StreamIpc\Transport\TimeoutException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class TaskOrchestrator
{
    public const OPTION_CONCURRENCY = 'concurrency';
    public const OPTION_UPDATE_INTERVAL = 'update-interval';
    public const DEFAULT_UPDATE_INTERVAL = 0.1;
    public const OPTION_MEMORY_LIMIT = 'memory-limit';
    public const DEFAULT_MEMORY_LIMIT = '-1';
    public const OPTION_RESUME = 'resume';

    public function __construct(
        private readonly IpcPeer $ipcPeer,
        private readonly ExecutionFactory $executionFactory,
        private readonly ProgressOutputFactory $outputFactory,
        private readonly IpcHandlerRegistryFactory $handlerFactory,
        private readonly ?MasterCacheServer $cache = null,
    ) {
    }

    /**
     * Run all tasks in the root tree, up to the configured concurrency
     * (or number of CPUs if none set), and return 0 if all succeeded or 1 if any failed.
     */
    public function run(string $commandName, TaskList $taskList, InputInterface $input, OutputInterface $output): int
    {
        $limit = $input->getOption(self::OPTION_MEMORY_LIMIT);
        if (is_string($limit) && $limit !== '') {
            ini_set('memory_limit', $limit);
        }
        $option = $input->getOption(self::OPTION_CONCURRENCY);
        $concurrency = is_numeric($option) ? (int)$option : CpuDetector::getCpuCount();

        $resumeId = $input->hasOption(self::OPTION_RESUME) ? $input->getOption(self::OPTION_RESUME) : null;
        if (is_string($resumeId) && $resumeId !== '') {
            $checkpoint = Checkpoint::load($resumeId, $commandName);
            $this->cache?->import($checkpoint->cache);
            $output->writeln('Resuming: ' . count($checkpoint->completed) . ' task(s) already done');
        } else {
            $checkpoint = Checkpoint::create($commandName);
        }

        $registry = $this->handlerFactory->create();
        $queue = new TaskTreeQueue($taskList, $concurrency, $checkpoint->completed);
        $result = $this->doRun(
            $commandName,
            $input->getOptions(),
            $queue,
            $this->outputFactory->create($taskList, $output, $registry, $input->getOptions()),
            $registry
        );

        if ($result === 0) {
            $checkpoint->delete();
        } else {
            $checkpoint->completed = $queue->getCompleted();
            $checkpoint->cache = $this->cache?->export() ?? [];
            $checkpoint->save();
            $output->writeln('To resume where it failed: ' . self::resumeCommand($checkpoint->id));
        }
        return $result;
    }

    /**
     * Rebuild the current command line with --resume <id> instead of any previous --resume.
     */
    public static function resumeCommand(string $id): string
    {
        /** @var string[] $argv */
        $argv = $_SERVER['argv'] ?? [];
        $args = [];
        for ($i = 0, $n = count($argv); $i < $n; $i++) {
            if ($argv[$i] === '--' . self::OPTION_RESUME) {
                $i++; // skip its value too
                continue;
            }
            if (str_starts_with($argv[$i], '--' . self::OPTION_RESUME . '=')) {
                continue;
            }
            $args[] = preg_match('~^[\w/.,:=%@+-]+$~', $argv[$i]) ? $argv[$i] : escapeshellarg($argv[$i]);
        }
        $args[] = '--' . self::OPTION_RESUME;
        $args[] = $id;
        return 'php ' . implode(' ', $args);
    }

    /**
     * Runs all tasks respecting dependencies and concurrency.
     * Returns 0 if all tasks succeeded, or 1 if any failed.
     */

    /**
     * @param array<string, mixed> $options
     */
    public function doRun(
        string $commandName,
        array $options,
        TaskTreeQueue $queue,
        ProgressOutput $output,
        IpcHandlerRegistry $handlerRegistry
    ): int {
        $hadError = false;
        /** @var TaskState[] $states */
        $states = [];

        $updateInterval = $options[self::OPTION_UPDATE_INTERVAL] ?? null;
        if (!is_numeric($updateInterval)) {
            throw new RuntimeException('Update interval must be a number');
        }
        $updateInterval = (float)$updateInterval;

        foreach ($queue as $task) {
            if ($task instanceof CompiledTaskNode) {
                // launch a new task
                $states[$task->id] = $state = $this->executionFactory->launch(
                    $commandName,
                    $task->id,
                    $options,
                    $queue->pendingCount(),
                    $handlerRegistry,
                    function (Throwable $e) use (&$states, &$hadError, $queue, $output) {
                        $failedId = $this->handleStreamException($e, $states, $queue, $output);
                        if ($failedId !== null) {
                            $hadError = true;
                            unset($states[$failedId]);
                        }
                    }
                );
                $output->onTaskStarted($state);
            } else {
                $this->ipcPeer->tickFor($updateInterval);
            }

            // poll each running task for completion
            foreach (array_keys($states) as $id) {
                // the stream-exception handler (which mutates $states by reference) may have already failed this task
                // @phpstan-ignore isset.offset
                if (!isset($states[$id])) {
                    continue;
                }
                $state = $states[$id];
                $execution = $state->getExecution();
                $exit = $execution?->getExitCode();
                if ($exit !== null) {
                    try {
                        $this->ipcPeer->tick(0.01);
                        // @phpstan-ignore isset.offset
                        if (!isset($states[$id])) {
                            continue; // failed by the stream-exception handler during this tick
                        }
                        if ($exit === 0) {
                            $queue->markCompleted($state->getTaskId());
                            $state->setStatus(TaskStatus::SUCCESS);
                            $output->onTaskCompleted($state);
                        } else {
                            $this->onError($state, $queue, $output);
                            $hadError = true;
                        }
                        unset($states[$id]);
                    } catch (TimeoutException) {
                    }
                }
            }

            $output->render();
        }

        return $hadError ? 1 : 0;
    }

    public function onError(TaskState $state, TaskTreeQueue $queue, ProgressOutput $output): void
    {
        $execution = $state->getExecution();
        if ($execution === null) {
            $output->log($state, 'No execution found for task ' . $state->getTaskId());
            $state->setStatus(TaskStatus::ERROR);
            $output->onTaskCompleted($state);
            return;
        }
        $result = $execution->kill();

        $message = 'Worker exited with code ' . var_export($result['exitCode'], true);
        $output->log($state, $message);

        $skipped = $queue->markFailed($state->getTaskId());
        $state->setStatus(TaskStatus::ERROR);
        $output->onTaskCompleted($state);
        foreach ($skipped as $sid) {
            $skipState = new TaskState($sid);
            $skipState->setStatus(TaskStatus::SKIP);
            $output->onTaskCompleted($skipState);
        }
    }

    /**
     * Handle a broken IPC stream by failing the matching task.
     *
     * @param TaskState[] $states
     * @return string|null The task ID that was failed, so the caller can drop it from its own state list.
     */
    public function handleStreamException(Throwable $e, array $states, TaskTreeQueue $queue, ProgressOutput $output): ?string
    {
        if (!$e instanceof InvalidStreamException) {
            throw $e;
        }
        foreach ($states as $state) {
            $execution = $state->getExecution();
            if ($execution?->getSession() === $e->getSession()) {
                $this->onError($state, $queue, $output);
                return $state->getTaskId();
            }
        }
        return null;
    }
}
