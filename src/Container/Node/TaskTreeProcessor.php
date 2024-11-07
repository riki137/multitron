<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Multitron\Impl\Task;
use Multitron\Util\CircularDependencyChecker;
use RuntimeException;

final class TaskTreeProcessor
{
    /** @var array<string, string[]> */
    private array $groupToLeafs = [];

    /** @var array<string, string[]> */
    private array $dependentIds = [];

    /** @var array<TaskNode> */
    private array $nodes = [];

    /** @var array<string, TaskNodeLeaf> */
    private array $leaves = [];

    /**
     * @param TaskNode $root The root node of the task tree.
     * @throws RuntimeException if tasks are defined twice or a task depends on itself.
     */
    public function __construct(TaskNode $root)
    {
        $dfsGraph = [];
        foreach ($root->getProcessedNodes() as $node) {
            $this->nodes[] = $node;

            $nodeId = $node->getId();
            if ($node instanceof TaskNodeLeaf) {
                if (isset($this->leaves[$nodeId])) {
                    throw new RuntimeException("Task '{$nodeId}' is defined multiple times.");
                }
                $this->leaves[$nodeId] = $node;
            }
            if ($nodeId !== null) {
                $this->processNodeGroups($node, $dfsGraph);
                $this->processNodeDependencies($node, $dfsGraph);
            }
        }

        (new CircularDependencyChecker())->check($dfsGraph);
    }

    /**
     * Processes the groups the node belongs to and updates the graph.
     *
     * @param array<string, string[]> &$dfsGraph
     */
    private function processNodeGroups(TaskNode $node, array &$dfsGraph): void
    {
        foreach ($node->getGroups() as $group) {
            $this->groupToLeafs[$group][] = $node->getId();
            $dfsGraph[$group][] = $node->getId();
        }
    }

    /**
     * Processes the dependencies of the node and updates the graph.
     *
     * @param array<string, string[]> &$dfsGraph
     * @throws RuntimeException
     */
    private function processNodeDependencies(TaskNode $node, array &$dfsGraph): void
    {
        $nodeId = $node->getId();
        $unpackedDeps = $this->unpackDependencies($node);

        foreach ($unpackedDeps as $unpackedDep) {
            $dfsGraph[$nodeId][] = $unpackedDep;
            $this->dependentIds[$nodeId][] = $unpackedDep;

            if ($nodeId === $unpackedDep) {
                throw new RuntimeException("Task '{$nodeId}' has a circular dependency on itself.");
            }
        }
    }

    /**
     * Unpacks dependencies for a given node.
     *
     * @return string[] The unique dependencies of the node.
     */
    private function unpackDependencies(TaskNode $node): array
    {
        $deps = [];
        foreach ($node->getDependencies() as $dep) {
            $this->unpackIfGroup($dep, $deps);
        }
        return array_unique($deps);
    }

    /**
     * Unpacks group dependencies recursively.
     *
     * @param string $id The ID to check if it's a group.
     * @param array &$deps The dependencies to accumulate.
     */
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
     * Returns all nodes.
     *
     * @return iterable<TaskNode>
     */
    public function getNodes(): iterable
    {
        return $this->nodes;
    }

    /**
     * Returns all leaf nodes.
     *
     * @return iterable<string, TaskNodeLeaf>
     */
    public function getLeaves(): iterable
    {
        return $this->leaves;
    }

    /**
     * Retrieve a task by its ID.
     *
     * @param string $id Task ID.
     * @return Task The requested task.
     *
     * @throws RuntimeException if the task is not found.
     */
    public function get(string $id): Task
    {
        if (isset($this->leaves[$id])) {
            return $this->leaves[$id]->getTask();
        }
        throw new RuntimeException("Task '{$id}' not found.");
    }

    /**
     * Checks if the identifier is a group.
     *
     * @param string $group
     * @return bool True if the identifier is a group, false otherwise.
     */
    public function isGroup(string $group): bool
    {
        return isset($this->groupToLeafs[$group]);
    }

    /**
     * Gets the leaf IDs in a group.
     *
     * @param string $group
     * @return string[] A list of leaf IDs.
     */
    public function getLeafIdsInGroup(string $group): array
    {
        return $this->groupToLeafs[$group] ?? [];
    }

    /**
     * Gets dependent IDs for a given node.
     *
     * @param TaskNodeLeaf|string $node
     * @return string[] The dependent IDs of the node.
     */
    public function getDependentIds(TaskNodeLeaf|string $node): array
    {
        if ($node instanceof TaskNodeLeaf) {
            return $this->dependentIds[$node->getId()] ?? [];
        }
        return $this->dependentIds[$node] ?? [];
    }
}
