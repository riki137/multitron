<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use LogicException;
use Multitron\Tree\TaskGroupNode;
use Multitron\Tree\TaskLeafNode;
use SplPriorityQueue;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A queue that produces the next ready TaskLeafNode, up to a concurrency limit.
 */
final class TaskQueue
{
    private TaskGraph $graph;

    /** @var SplPriorityQueue<int, string> */
    private SplPriorityQueue $readyQueue;

    private int $maxConcurrent;

    /** @var array<string, bool> */
    private array $running = [];

    /**
     * @param int $maxConcurrent How many tasks to allow in flight.
     * @throws LogicException if maxConcurrent < 1 or dependencies invalid.
     */
    public function __construct(TaskList $taskList, InputInterface $input, int $maxConcurrent)
    {
        if ($maxConcurrent < 1) {
            throw new LogicException('Concurrency must be at least 1.');
        }
        $this->maxConcurrent = $maxConcurrent;
        $this->graph = TaskGraph::buildFrom($taskList, $input);

        /** @var SplPriorityQueue<int, string> $queue */
        $queue = new SplPriorityQueue();
        $this->readyQueue = $queue;
        foreach ($this->graph->initialReadyTasks() as $id) {
            $priority = count($this->graph->getDependents($id));
            $this->readyQueue->insert($id, $priority);
        }

        $this->running = [];
    }

    /**
     * Return the next TaskLeafNode that's ready, or true if there are more tasks to run.
     */
    public function getNextTask(): TaskLeafNode|bool
    {
        // respect concurrency limit
        if (count($this->running) >= $this->maxConcurrent) {
            return true;
        }

        // pull highest-priority ready task
        while (!$this->readyQueue->isEmpty()) {
            $id = $this->readyQueue->extract();
            /** @var string $id */
            if (isset($this->running[$id]) || $this->graph->isCompleted($id)) {
                continue;
            }

            $node = $this->graph->getNode($id);

            if ($node instanceof TaskGroupNode) {
                $newIds = $this->graph->complete($id);
                foreach ($newIds as $newId) {
                    $prio = count($this->graph->getDependents($newId));
                    $this->readyQueue->insert($newId, $prio);
                }
                continue;
            }

            // mark running and return the node
            $this->running[$id] = true;
            /** @var TaskLeafNode $node */
            return $node;
        }

        return count($this->running) > 0;
    }

    /**
     * Mark a task as finished, free up a slot, and enqueue any newly-ready tasks.
     *
     * @param string $id The ID of the task that just completed.
     * @throws LogicException if you try to complete a task that wasn’t running.
     */
    public function completeTask(string $id): void
    {
        if (!isset($this->running[$id])) {
            throw new LogicException("Cannot complete task '$id' because it is not marked running.");
        }
        unset($this->running[$id]);
        $newReadyIds = $this->graph->complete($id);
        foreach ($newReadyIds as $newId) {
            $prio = count($this->graph->getDependents($newId));
            $this->readyQueue->insert($newId, $prio);
        }
    }

    /**
     * Mark a task as failed and skip all dependents.
     *
     * @return string[] IDs of tasks that were skipped due to this failure.
     */
    public function failTask(string $id): array
    {
        if (!isset($this->running[$id])) {
            throw new LogicException("Cannot fail task '$id' because it is not marked running.");
        }
        unset($this->running[$id]);
        return $this->graph->skipDependents($id);
    }
}
