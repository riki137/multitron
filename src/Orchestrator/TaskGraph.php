<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use LogicException;
use Multitron\Tree\TaskNode;
use Multitron\Orchestrator\TaskList;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Internal graph of task dependencies, with topological order support.
 */
class TaskGraph
{
    /** @var array<string, TaskNode> */
    private array $nodes = [];

    /** @var array<string, int> */
    private array $inDegree = [];

    /** @var array<string, string[]> */
    private array $dependents = [];

    /** @var array<string, bool> */
    private array $completed = [];

    private function __construct()
    {
    }

    /**
     * Build the graph from the root TaskNode.
     * @throws LogicException on unknown dependencies or cycles.
     */
    public static function buildFrom(TaskList $taskList, InputInterface $options): self
    {
        $g = new self();
        $g->nodes = $taskList->getNodes();
        // Initialize in-degrees
        foreach ($g->nodes as $id => $node) {
            $g->inDegree[$id] = 0;
            $g->dependents[$id] = [];
        }
        // Build edges
        foreach ($g->nodes as $id => $node) {
            foreach ($node->getDependencies($options) as $dep) {
                if (!isset($g->nodes[$dep])) {
                    throw new LogicException("Task '$id' depends on unknown '$dep'.");
                }

                if ($taskList->isGroup($dep)) {
                    if ($taskList->isMemberOf($dep, $id)) {
                        $g->inDegree[$id]++;
                        $g->dependents[$dep][] = $id;
                    } else {
                        foreach ($taskList->getGroupMembers($dep) as $member) {
                            $g->inDegree[$id]++;
                            $g->dependents[$member][] = $id;
                        }
                    }
                    continue;
                }

                $g->inDegree[$id]++;
                $g->dependents[$dep][] = $id;
            }
        }

        self::assertAcyclic($g);
        return $g;
    }

    private static function assertAcyclic(self $g): void
    {
        $inDegree = $g->inDegree;
        $queue    = array_keys(array_filter($inDegree, fn ($deg) => $deg === 0));

        $visited = 0;
        while ($queue) {
            $current = array_shift($queue);
            $visited++;
            foreach ($g->dependents[$current] as $dep) {
                if (!array_key_exists($dep, $inDegree)) {
                    continue;
                }
                if (--$inDegree[$dep] === 0) {
                    $queue[] = $dep;
                }
            }
            unset($inDegree[$current]);
        }

        if ($visited !== count($g->nodes)) {
            $cycleNodes = array_keys(array_filter($inDegree, fn ($d) => $d > 0));
            sort($cycleNodes);
            throw new LogicException('Cyclic dependency detected among tasks: ' . implode(', ', $cycleNodes));
        }
    }

    /**
     * Return the IDs of tasks with no unmet dependencies.
     *
     * @return string[]
     */
    public function initialReadyTasks(): array
    {
        return array_keys(array_filter($this->inDegree, fn($deg) => $deg === 0));
    }

    /**
     * Get the TaskNode by ID.
     */
    public function getNode(string $id): TaskNode
    {
        return $this->nodes[$id];
    }

    /**
     * Check if a task is marked completed.
     */
    public function isCompleted(string $id): bool
    {
        return isset($this->completed[$id]);
    }

    /**
     * Mark a task as complete and enqueue new ready tasks.
     *
     * @return string[] Newly ready task IDs
     */
    public function complete(string $id): array
    {
        $this->completed[$id] = true;
        unset($this->inDegree[$id]);
        $ready = [];
        foreach ($this->dependents[$id] as $dep) {
            if (isset($this->inDegree[$dep]) && --$this->inDegree[$dep] === 0) {
                $ready[] = $dep;
            }
        }
        return $ready;
    }

    /**
     * @return string[]
     */
    public function getDependents(string $id): array
    {
        return $this->dependents[$id] ?? [];
    }

    /**
     * Mark the given task and all its dependents as completed without
     * enqueuing any of them for execution. Returns all skipped task IDs
     * (excluding the provided one).
     *
     * @return string[]
     */
    public function skipDependents(string $id): array
    {
        $this->markCompleted($id);
        $skipped = [];
        foreach ($this->dependents[$id] as $dep) {
            $skipped = array_merge($skipped, $this->skipRecursive($dep));
        }
        return $skipped;
    }

    /**
     * @return string[]
     */
    private function skipRecursive(string $id): array
    {
        if ($this->isCompleted($id)) {
            return [];
        }
        $this->markCompleted($id);
        $result = [$id];
        foreach ($this->dependents[$id] as $dep) {
            $result = array_merge($result, $this->skipRecursive($dep));
        }
        return $result;
    }

    private function markCompleted(string $id): void
    {
        $this->completed[$id] = true;
        unset($this->inDegree[$id]);
    }
}
