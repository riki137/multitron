<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Tree\TaskNode;
use SplPriorityQueue;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A queue that produces the next ready TaskNode, up to a concurrency limit.
 */
final class TaskQueue
{
    private array $nodes;

    private SplPriorityQueue $ready;

    private array $reverseDeps = [];

    private array $prereqCount = [];

    private array $pending = [];

    private array $running = [];

    private int $concurrency;

    /**
     * @param TaskNode[] $nodes        Flat map of leaf TaskNode objects, keyed by task ID.
     * @param InputInterface $input    (unused for now—but available for future flags)
     * @param int $concurrency         Max number of tasks to run at once.
     */
    public function __construct(array $nodes, InputInterface $input, int $concurrency)
    {
        $this->nodes = $nodes;
        $this->concurrency = $concurrency;

        // Build prereq counts and reverse‐dependency map
        foreach ($nodes as $id => $node) {
            $deps = $node->dependencies;
            $this->prereqCount[$id] = count($deps);
            $this->pending[$id] = true;
            foreach ($deps as $depId) {
                $this->reverseDeps[$depId][] = $id;
            }
        }

        // Prime the ready queue with all tasks having zero prerequisites
        $this->ready = new SplPriorityQueue();
        foreach ($this->prereqCount as $id => $count) {
            if ($count === 0) {
                // All priorities are equal for now; you can vary this if you want custom ordering
                $this->ready->insert($id, 0);
            }
        }
    }

    /**
     * Get the next task to start.
     *
     * @return TaskNode    If a new task can be launched now.
     * @return true        If no task is ready but some are still running (please wait).
     * @return false       If no tasks are ready and none are running (we’re done).
     */
    public function getNextTask(): TaskNode|bool
    {
        // Clean out any stale IDs in the ready queue (skipped or already started).
        while (!$this->ready->isEmpty()) {
            $top = $this->ready->top();
            if (isset($this->pending[$top])) {
                break;
            }
            $this->ready->extract();
        }

        // If we can start another task, pop it and return the TaskNode
        if (count($this->running) < $this->concurrency && !$this->ready->isEmpty()) {
            $id = $this->ready->extract();
            $this->running[$id] = true;
            unset($this->pending[$id]);
            return $this->nodes[$id];
        }

        // If there are tasks still running, ask caller to wait
        if (!empty($this->running)) {
            return true;
        }

        // Nothing running, nothing ready → we’re done
        return false;
    }

    /**
     * Mark a task as completed successfully.
     * Enqueue any dependents that are now unblocked.
     */
    public function completeTask(string $id): void
    {
        unset($this->running[$id]);

        foreach ($this->reverseDeps[$id] ?? [] as $childId) {
            $this->prereqCount[$childId]--;
            if ($this->prereqCount[$childId] === 0) {
                $this->ready->insert($childId, 0);
            }
        }
    }

    /**
     * Mark a task as failed.
     * Returns the list of all tasks that (transitively) depend on it,
     * so they can be marked as skipped.
     *
     * @return string[]  IDs of tasks to skip
     */
    public function failTask(string $id): array
    {
        unset($this->running[$id]);
        $skipped = [];
        $stack = $this->reverseDeps[$id] ?? [];

        // Depth-first: find all downstream tasks that are still pending
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (isset($this->pending[$current])) {
                $skipped[] = $current;
                unset($this->pending[$current]);
                // enqueue its dependents for skipping too
                foreach ($this->reverseDeps[$current] ?? [] as $child) {
                    $stack[] = $child;
                }
            }
        }

        return $skipped;
    }
}
