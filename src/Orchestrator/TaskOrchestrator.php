<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Execution\CpuDetector;
use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
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

final class TaskOrchestrator
{
    public const OPTION_CONCURRENCY = 'concurrency';
    public const OPTION_UPDATE_INTERVAL = 'update-interval';
    public const DEFAULT_UPDATE_INTERVAL = 0.1;
    public const OPTION_MEMORY_LIMIT = 'memory-limit';
    public const DEFAULT_MEMORY_LIMIT = '-1';

    public function __construct(
        private readonly IpcPeer $ipcPeer,
        private readonly ExecutionFactory $executionFactory,
        private readonly ProgressOutputFactory $outputFactory,
        private readonly IpcHandlerRegistryFactory $handlerFactory,
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

        $registry = $this->handlerFactory->create();
        return $this->doRun(
            $commandName,
            $input->getOptions(),
            new TaskTreeQueue($taskList, $concurrency),
            $this->outputFactory->create($taskList, $output, $registry, $input->getOptions()),
            $registry
        );
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
                    $handlerRegistry
                );
                $output->onTaskStarted($state);
            } else {
                try {
                    $this->ipcPeer->tickFor($updateInterval);
                } catch (InvalidStreamException $e) {
                    $this->handleStreamException($e, $states, $queue, $output);
                }
            }

            // poll each running task for completion
            foreach ($states as $id => $state) {
                $execution = $state->getExecution();
                $exit = $execution?->getExitCode();
                if ($exit !== null) {
                    try {
                        $this->ipcPeer->tick(0.01);
                        if ($exit === 0) {
                            $queue->markCompleted($state->getTaskId());
                            $state->setStatus(TaskStatus::SUCCESS);
                            $output->onTaskCompleted($state);
                            unset($states[$id]);
                        } else {
                            $this->onError($state, $queue, $output);
                            $hadError = true;
                        }
                    } catch (TimeoutException) {
                    } catch (InvalidStreamException $e) {
                        $this->handleStreamException($e, $states, $queue, $output);
                        continue; // skip this iteration as the state may have been removed
                    }
                }
            }

            $output->render();
        }

        return $hadError ? 1 : 0;
    }

    public function onError(TaskState $state, TaskTreeQueue $queue, ProgressOutput $output): void
    {
        $result = $state->getExecution()->kill();
        $output->log(
            $state,
            'Worker exited with code ' . var_export($result['exitCode'], true),
        );
        $stdout = trim($result['stdout']);
        $stderr = trim($result['stderr']);
        if ($stdout === '' && $stderr === '') {
            $output->log($state, 'Nothing left in stdout and stderr streams.');
        } else {
            if ($stdout !== '') {
                $output->log($state, 'STDOUT: ' . $stdout);
            }
            if ($stderr !== '') {
                $output->log($state, 'STDERR: ' . $stderr);
            }
        }

        $skipped = $queue->markFailed($state->getTaskId());
        $state->setStatus(TaskStatus::ERROR);
        $output->onTaskCompleted($state);
        foreach ($skipped as $sid) {
            $skipState = new TaskState($sid);
            $skipState->setStatus(TaskStatus::SKIP);
            $output->onTaskCompleted($skipState);
        }
    }

    public function handleStreamException(InvalidStreamException $e, array $states, TaskTreeQueue $queue, ProgressOutput $output): void
    {
        foreach ($states as $state) {
            if ($state->getExecution()->getSession() === $e->getSession()) {
                $this->onError($state, $queue, $output);
                unset($states[$state->getTaskId()]);
                return;
            }
        }
    }
}
