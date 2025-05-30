<?php
declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Tree\TaskNode;
use Symfony\Component\Console\Input\InputInterface;

final class TaskQueue
{
    private array $nodes;

    private int $concurrency;

    /** taskId => number of unfinished prereqs */
    private array $prereqCount = [];

    /** taskId => [ childId, ... ] */
    private array $reverseDeps = [];

    /** taskId => true while not yet started or skipped */
    private array $pending = [];

    /** taskId => true while currently running */
    private array $running = [];

    /** queue of ready task IDs */
    private array $ready = [];

    /**
     * @param TaskNode[] $nodes keyed by ID
     */
    public function __construct(array $nodes, InputInterface $input, int $concurrency)
    {
        $this->nodes = $nodes;
        $this->concurrency = $concurrency;

        // Build prereq counts & reverse‐deps
        foreach ($nodes as $id => $node) {
            $this->prereqCount[$id] = count($node->dependencies);
            $this->pending[$id] = true;

            foreach ($node->dependencies as $depId) {
                $this->reverseDeps[$depId][] = $id;
            }
        }

        // Initial ready list: those with zero prereqs
        foreach ($this->prereqCount as $id => $count) {
            if ($count === 0) {
                $this->ready[] = $id;
            }
        }
    }

    /**
     * @return TaskNode|bool next task to launch, true: nothing ready but still running → wait, false: nothing running → we’re done
     */
    public function getNextTask(): TaskNode|bool
    {
        // purge any tasks that were started or skipped
        $this->ready = array_values(array_filter(
            $this->ready,
            fn(string $id) => isset($this->pending[$id])
        ));

        // can we start something?
        if (count($this->running) < $this->concurrency && !empty($this->ready)) {
            // pick the one that unblocks the most dependents
            usort($this->ready, fn($a, $b) => count($this->reverseDeps[$b] ?? []) <=> count($this->reverseDeps[$a] ?? []));

            $id = array_shift($this->ready);
            unset($this->pending[$id]);
            $this->running[$id] = true;
            return $this->nodes[$id];
        }

        // nothing ready but some still running?
        return !empty($this->running);
    }

    public function completeTask(string $id): void
    {
        unset($this->running[$id]);

        // decrement children; enqueue any that now have zero prereqs
        foreach ($this->reverseDeps[$id] ?? [] as $child) {
            if (--$this->prereqCount[$child] === 0) {
                $this->ready[] = $child;
            }
        }
    }

    /**
     * @return string[] all (transitive) dependents of $id that should be skipped
     */
    public function failTask(string $id): array
    {
        unset($this->running[$id]);
        $skipped = [];
        $stack = $this->reverseDeps[$id] ?? [];

        while (!empty($stack)) {
            $curr = array_pop($stack);
            if (isset($this->pending[$curr])) {
                $skipped[] = $curr;
                unset($this->pending[$curr]);
                $stack = array_merge($stack, $this->reverseDeps[$curr] ?? []);
            }
        }

        return $skipped;
    }
}
