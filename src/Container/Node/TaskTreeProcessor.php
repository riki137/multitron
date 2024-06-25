<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Multitron\Impl\Task;
use Multitron\Util\CircularDependencyChecker;
use RuntimeException;

class TaskTreeProcessor
{
    /** @var array<string, string[]> */
    private array $groupToLeafs = [];

    /** @var array<string, string[]> */
    private array $dependencies = [];

    /** @var array<string, TaskLeafNode> */
    private array $nodes = [];

    public function __construct(private readonly TaskGroupNode $node)
    {
    }

    private function index(): self
    {
        if ($this->nodes !== []) {
            return $this;
        }
        $dfsGraph = [];
        foreach ($this->node->getTasks() as $node) {
            if (isset($this->nodes[$node->getId()])) {
                throw new RuntimeException("Task {$node->getId()} is being defined twice");
            }
            $this->nodes[$node->getId()] = $node;
            foreach ($node->getGroups() as $group) {
                $this->groupToLeafs[$group][] = $node->getId();
                if (is_string($dfsGraph[$group] ?? null)) {
                    throw new RuntimeException("Group $group is already a task");
                }
                $dfsGraph[$group][] = $node->getId();
            }
            foreach ($node->getDependencies() as $dep) {
                $dfsGraph[$node->getId()][] = $dep;
            }
            foreach ($this->rawDependencies($node) as $dep) {
                $this->dependencies[$node->getId()][] = $dep;
                if ($node->getId() === $dep) {
                    throw new RuntimeException("Task {$node->getId()} depends on itself");
                }
            }
        }
        $dfs = new CircularDependencyChecker();
        $dfs->check($dfsGraph);

        return $this;
    }

    private function extractCycle($path, $startNode): array
    {
        $cycle = [];
        $found = false;
        foreach (array_keys($path) as $nodeId) {
            if ($nodeId == $startNode) {
                $found = true;
            }
            if ($found) {
                $cycle[] = $nodeId;
            }
        }
        $cycle[] = $startNode; // To complete the cycle
        return $cycle;
    }

    private function rawDependencies(TaskNode $node): array
    {
        $deps = [];
        foreach ($node->getDependencies() as $dep) {
            $this->unpackIfGroup($dep, $deps);
        }
        return array_unique($deps);
    }

    private function unpackIfGroup(string $id, array &$deps): void
    {
        if ($this->isGroup($id)) {
            foreach ($this->getLeafIdsInGroup($id) as $groupId) {
                $this->unpackIfGroup($groupId, $deps);
            }
        } else {
            $deps[] = $id;
        }
    }

    /**
     * @return array<string, TaskLeafNode>
     */
    public function getNodes(): array
    {
        return $this->index()->nodes;
    }

    public function get(string $id): Task
    {
        if (isset($this->nodes[$id])) {
            return $this->nodes[$id]->getTask();
        }

        foreach ($this->node->getTasks() as $node) {
            if ($node->getId() === $id) {
                return $node->getTask();
            }
        }
        throw new RuntimeException("Task $id not found");
    }

    public function isGroup(string $group): bool
    {
        return isset($this->index()->groupToLeafs[$group]);
    }

    public function getLeafIdsInGroup(string $group): array
    {
        return $this->index()->groupToLeafs[$group] ?? [];
    }

    public function getDependencies(TaskNode $node): array
    {
        return $this->index()->dependencies[$node->getId()] ?? [];
    }

    /**
     * This function sorts the task nodes by priority, with the highest priority first.
     *
     * @param array $nodes array of task nodes, keyed by task id
     * @param array<string> $finished array of finished task ids
     * @return void
     */
    public function ksortByPriority(array &$nodes, array $finished): void
    {
        $this->index();
        uksort($nodes, function ($a, $b) use ($finished) {
            $aDeps = $this->dependencies[$a] ?? [];
            $bDeps = $this->dependencies[$b] ?? [];
            $aUnfinished = array_diff($aDeps, $finished);
            $bUnfinished = array_diff($bDeps, $finished);
            $aPriority = count($aUnfinished);
            $bPriority = count($bUnfinished);
            return $aPriority <=> $bPriority;
        });
    }
}
