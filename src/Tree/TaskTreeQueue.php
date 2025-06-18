<?php

declare(strict_types=1);

namespace Multitron\Tree;

use LogicException;
use Multitron\Orchestrator\TaskList;

final class TaskTreeQueue
{
    /** @var CompiledTaskNode[] Tasks not yet dispatched */
    private array $pendingTasks;

    /** @var CompiledTaskNode[] Tasks dispatched but not yet completed */
    private array $runningTasks = [];

    /** @var string[] Completed task IDs for quick lookup */
    private array $completedTasks = [];

    /**
     * Reverse dependency map:
     *   taskId => [dependentTaskId, ...]
     * @var array<string, string[]>
     */
    private array $dependentsMap = [];

    /** @var CompiledTaskNode[] Tasks whose dependencies are already met */
    private array $availableTasks = [];

    public function __construct(TaskList $tasks, private readonly int $concurrencyLimit = 1)
    {
        if ($this->concurrencyLimit < 1) {
            throw new LogicException('Concurrency limit must be at least 1.');
        }

        $this->pendingTasks = $tasks->toArray();

        // 1) ensure every task ID has an entry
        foreach ($this->pendingTasks as $id => $_) {
            $this->dependentsMap[$id] = [];
        }

        // 2) build reverse map and seed the “ready” queue
        foreach ($this->pendingTasks as $id => $task) {
            foreach ($task->dependencies as $depId) {
                if (! array_key_exists($depId, $this->pendingTasks)) {
                    throw new LogicException("Task '$id' has unknown dependency '$depId'.");
                }
                $this->dependentsMap[$depId][] = $id;
            }
            if (empty($task->dependencies)) {
                $this->availableTasks[$id] = $task;
            }
        }
    }

    /**
     * @return CompiledTaskNode  next task to run
     *         true               wait (none ready yet, but some running)
     *         false              all done
     * @throws LogicException     on deadlock
     */
    public function getNextTask(): CompiledTaskNode|bool
    {
        // no ready tasks right now…
        if ([] === $this->availableTasks) {
            // …and none running
            if ([] === $this->runningTasks) {
                // …and none pending
                if ([] === $this->pendingTasks) {
                    return false; // done
                }
                throw new LogicException('Deadlock detected: remaining tasks have unmet dependencies.');
            }
            return true; // wait for in-flight work
        }

        // respect concurrency limit
        if (count($this->runningTasks) >= $this->concurrencyLimit) {
            return true;
        }

        // pick the ready task with the most downstream work
        $bestId = null;
        $bestScore = -1;
        foreach ($this->availableTasks as $task) {
            $score = $this->calculatePriorityScore($task);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $task->id;
            }
        }

        $task = $this->availableTasks[$bestId];
        unset(
            $this->availableTasks[$bestId],
            $this->pendingTasks[$bestId]
        );
        $this->runningTasks[$bestId] = $task;

        return $task;
    }

    public function markCompleted(string $taskId): void
    {
        if (! isset($this->runningTasks[$taskId])) {
            return; // duplicate or unknown
        }

        unset($this->runningTasks[$taskId]);
        $this->completedTasks[$taskId] = $taskId;

        // any dependents whose *all* deps are now done become available
        foreach ($this->dependentsMap[$taskId] as $depId) {
            if (isset($this->pendingTasks[$depId]) &&
                $this->areDependenciesMet($this->pendingTasks[$depId])
            ) {
                $this->availableTasks[$depId] = $this->pendingTasks[$depId];
            }
        }
    }

    /**
     * Mark this one failed, skip it and *all* its descendants.
     *
     * @return string[] IDs of tasks that were skipped
     */
    public function markFailed(string $taskId): array
    {
        if (! isset($this->runningTasks[$taskId])) {
            return [];
        }

        unset($this->runningTasks[$taskId]);
        $this->completedTasks[$taskId] = $taskId;

        $skipped = [];
        $queue = [$taskId];

        // BFS through the reverse-dependency graph
        while ($queue) {
            $current = array_shift($queue);
            foreach ($this->dependentsMap[$current] as $childId) {
                if (isset($this->pendingTasks[$childId])) {
                    $skipped[] = $childId;
                    unset($this->pendingTasks[$childId], $this->availableTasks[$childId]);
                    $queue[] = $childId;
                }
            }
        }

        return $skipped;
    }

    private function areDependenciesMet(CompiledTaskNode $task): bool
    {
        foreach ($task->dependencies as $depId) {
            if (! isset($this->completedTasks[$depId])) {
                return false;
            }
        }
        return true;
    }

    private function calculatePriorityScore(CompiledTaskNode $task): int
    {
        $score = 0;
        foreach ($this->dependentsMap[$task->id] as $depId) {
            if (isset($this->pendingTasks[$depId])) {
                $score++;
            }
        }
        return $score;
    }

    public function pendingCount(): int
    {
        return count($this->pendingTasks);
    }

    public function hasUnfinishedTasks(): bool
    {
        return [] !== $this->pendingTasks || [] !== $this->runningTasks;
    }
}
