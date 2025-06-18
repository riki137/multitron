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
use StreamIpc\IpcPeer;
use StreamIpc\Transport\TimeoutException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskOrchestrator
{
    public const OPTION_CONCURRENCY = 'concurrency';
    public const OPTION_UPDATE_INTERVAL = 'update-interval';
    public const DEFAULT_UPDATE_INTERVAL = 0.1;

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
        $states = [];

        $updateInterval = $options[self::OPTION_UPDATE_INTERVAL] ?? null;
        if (!is_numeric($updateInterval)) {
            throw new RuntimeException('Update interval must be a number');
        }
        $updateInterval = (float)$updateInterval;

        while (true) {
            $task = $queue->getNextTask();

            if ($task instanceof CompiledTaskNode) {
                // launch a new task
                $execution = $this->executionFactory->launch($commandName, $task->id, $options, $queue->pendingCount());
                $states[$task->id] = $state = new TaskState($task->id, $execution);
                $handlerRegistry->attach($state);
                $output->onTaskStarted($state);
            } elseif ($task === false) {
                // $task is false -> no tasks left and none running
                break;
            } else {
                // $task is true -> wait for running tasks to finish
                $this->ipcPeer->tickFor($updateInterval);
            }

            // poll each running task for completion
            foreach ($states as $id => $state) {
                $execution = $state->getExecution();
                $exit = $execution?->getExitCode();
                if ($exit !== null) {
                    try {
                        $this->ipcPeer->tick(0.01);
                    } catch (TimeoutException) {
                    }
                    if ($exit === 0) {
                        $queue->markCompleted($state->getTaskId());
                        $state->setStatus(TaskStatus::SUCCESS);
                        $output->onTaskCompleted($state);
                    } else {
                        $skipped = $queue->markFailed($state->getTaskId());
                        $state->setStatus(TaskStatus::ERROR);
                        $output->onTaskCompleted($state);
                        foreach ($skipped as $sid) {
                            $skipState = new TaskState($sid);
                            $skipState->setStatus(TaskStatus::SKIP);
                            $output->onTaskCompleted($skipState);
                        }
                        $hadError = true;
                    }
                    unset($states[$id]);
                }
            }

            $output->render();
        }

        return $hadError ? 1 : 0;
    }
}
