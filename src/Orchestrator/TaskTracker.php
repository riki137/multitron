<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Console\TableOutput;
use Multitron\Execution\ExecutionFactory;
use Multitron\Tree\TaskNode;

class TaskTracker
{
    private TaskQueue $queue;
    /** @var array<string, TaskState> */
    private array $states = [];

    public function __construct(
        private readonly int $maxConcurrent,
        TaskNode $root,
        private readonly ExecutionFactory $processFactory,
        private readonly TableOutput $output,
    ) {
        $this->queue = new TaskQueue($root, $maxConcurrent);
    }

    /**
     * Runs all tasks respecting dependencies and concurrency.
     * Returns 0 if all tasks succeeded, or 1 if any failed.
     */
    public function run(): int
    {
        $hadError = false;

        while (true) {
            $task = $this->queue->getNextTask();

            if ($task instanceof TaskNode) {
                // launch a new task
                $execution = $this->processFactory->launch($task->getFactory()());
                $this->states[$task->getId()] = $state = new TaskState($task->getId(), $execution);
                $this->output->updateTask($state);
            } elseif ($task !== true) {
                // $task is false -> no tasks left and none running
                break;
            }

            // poll each running task for completion
            foreach ($this->states as $id => $state) {
                $exit = $state->getExecution()->statusCode();
                if ($exit !== null) {
                    // mark it completed in the graph
                    $this->queue->completeTask($id);
                    $state->setStatus(TaskStatus::SUCCESS);
                    $this->output->completeTask($state);
                    unset($this->states[$id]);

                    // record if anything failed
                    if ($exit !== 0) {
                        $hadError = true;
                    }
                }
            }

            // throttle when there's nothing new to launch
            if (! ($task instanceof TaskNode)) {
                usleep(50_000);
            }
        }

        return $hadError ? 1 : 0;
    }
}
