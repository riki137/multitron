<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Multitron\Impl\Task;
use RuntimeException;
use Traversable;

class TaskTreeProcessor
{
    private array $groupIndex = [];
    private array $nodes = [];

    public function __construct(private readonly TaskGroupNode $node)
    {
    }

    /**
     * @return array<string, TaskLeafNode>
     */
    public function getNodes(): array
    {
        if ($this->nodes !== []) {
            return $this->nodes;
        }
        foreach ($this->node->getTasks() as $node) {
            $this->nodes[$node->getId()] = $node;
        }
        return $this->nodes;
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
        return isset($this->getGroupIndex()[$group]);
    }

    public function getIdsInGroup(string $group): array
    {
        return $this->getGroupIndex()[$group] ?? [];
    }

    private function getGroupIndex(): array
    {
        if ($this->groupIndex !== []) {
            return $this->groupIndex;
        }
        foreach ($this->getNodes() as $node) {
            foreach ($node->getGroups() as $group) {
                $this->groupIndex[$group][] = $node->getId();
            }
        }
        return $this->groupIndex;
    }
}
