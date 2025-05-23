<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Tree\TaskFilterNode;
use Multitron\Tree\TaskGroupNode;
use Multitron\Tree\TaskNode;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeBuilderFactory;
use Symfony\Component\Console\Input\InputInterface;

class TaskList
{
    /** @var array<string, TaskNode> */
    private array $nodes = [];

    /** @var array<string, string[]> */
    private array $groupMembers = [];

    private TaskTreeBuilder $builder;

    public function __construct(TaskTreeBuilderFactory $factory, TaskNode $root, InputInterface $options)
    {
        $this->builder = $factory->create();
        $this->collect($root, $options, [], []);
    }

    /**
     * @param array<string, TaskGroupNode> $groups
     * @param TaskFilterNode[] $filters
     */
    private function collect(TaskNode $node, InputInterface $options, array $groups, array $filters): void
    {
        $id = $node->getId();
        if (isset($this->nodes[$id])) {
            return;
        }
        if ($node instanceof TaskFilterNode) {
            $filters[] = $node;
        }
        if ($this->passedFilter($node, $groups, $filters)) {
            $this->nodes[$id] = $node;
        }
        foreach ($groups as $groupId => $groupNode) {
            $this->groupMembers[$groupId][$id] = $id;
        }
        if ($node instanceof TaskGroupNode) {
            $groups[$id] = $node;
            $node->getChildren($this->builder, $options);
            foreach ($this->builder->consume() as $child) {
                $this->collect($child, $options, $groups, $filters);
            }
        }
    }

    /**
     * @param array<string, TaskGroupNode> $groups
     * @param TaskFilterNode[] $filters
     */
    private function passedFilter(TaskNode $node, array $groups, array $filters): bool
    {
        foreach ($filters as $filter) {
            if (!$filter->filter($node, $groups)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array<string, TaskNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return string[]
     */
    public function getGroupMembers(string $id): array
    {
        return $this->groupMembers[$id] ?? [];
    }

    public function isGroup(string $id): bool
    {
        return isset($this->groupMembers[$id]);
    }

    public function isMemberOf(string $groupId, string $leafId): bool
    {
        return isset($this->groupMembers[$groupId][$leafId]);
    }
}
