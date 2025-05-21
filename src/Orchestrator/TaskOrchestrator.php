<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Orchestrator\Output\ProgressOutput;
use Multitron\Orchestrator\Output\ProgressOutputFactory;
use Multitron\Tree\TaskNode;
use PhpStreamIpc\IpcPeer;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskOrchestrator
{
    public const OPTION_CONCURRENCY = 'concurrency';

    public function __construct(
        private readonly IpcPeer $ipcPeer,
        private readonly ContainerInterface $container,
        private readonly ExecutionFactory $executionFactory,
        private readonly ProgressOutputFactory $outputFactory,
        private readonly IpcHandlerRegistryFactory $handlerFactory,
    ) {
    }

    /**
     * Run all tasks in the root tree, up to the configured concurrency
     * (or number of CPUs if none set), and return 0 if all succeeded or 1 if any failed.
     */
    public function run(string $commandName, TaskNode $root, InputInterface $input, OutputInterface $output): int
    {
        $concurrency = $input->getOption(self::OPTION_CONCURRENCY) ?? $this->detectCpuCount();

        $registry = $this->handlerFactory->create();
        return $this->doRun(
            $commandName,
            $input->getOptions(),
            new TaskQueue($this->container, $root, $concurrency, $input),
            $this->outputFactory->create($output, $registry),
            $registry
        );
    }

    /**
     * Runs all tasks respecting dependencies and concurrency.
     * Returns 0 if all tasks succeeded, or 1 if any failed.
     */
    public function doRun(
        string $commandName,
        array $options,
        TaskQueue $queue,
        ProgressOutput $output,
        IpcHandlerRegistry $handlerRegistry
    ): int {
        $hadError = false;
        $states = [];
        while (true) {
            $task = $queue->getNextTask();

            if ($task instanceof TaskNode) {
                // launch a new task
                $execution = $this->executionFactory->launch($commandName, $task->getId(), $options);
                $states[$task->getId()] = $state = new TaskState($task->getId(), $execution);
                $handlerRegistry->attach($state);
                $output->onTaskStarted($state);
            } elseif ($task !== true) {
                // $task is false -> no tasks left and none running
                break;
            }

            // poll each running task for completion
            foreach ($states as $id => $state) {
                $exit = $state->getExecution()->getExitCode();
                if ($exit !== null) {
                    // mark it completed in the graph
                    $queue->completeTask($id);
                    $state->setStatus(TaskStatus::SUCCESS);
                    $output->onTaskCompleted($state);
                    unset($states[$id]);

                    // record if anything failed
                    if ($exit !== 0) {
                        $hadError = true;
                    }
                }
            }
            $output->render();

            $this->ipcPeer->tickFor(0.1);
        }

        return $hadError ? 1 : 0;
    }

    /**
     * Attempt to discover the number of logical CPUs on this host.
     */
    private function detectCpuCount(): int
    {
        if (stripos(PHP_OS, 'WIN') === 0) { // Windows
            $output = shell_exec('wmic cpu get NumberOfLogicalProcessors /value');
            if ($output) {
                $matches = [];
                if (preg_match('/NumberOfLogicalProcessors=(\d+)/', $output, $matches)) {
                    return (int)$matches[1];
                }
            }
        } elseif (stripos(PHP_OS, 'Darwin') !== false || stripos(PHP_OS, 'Linux') !== false) { // macOS or Linux
            $output = shell_exec('nproc');
            if (is_string($output) && is_numeric(trim($output))) {
                return max(1, (int)trim($output));
            }
        }

        // Fallback
        return 4;
    }
}
