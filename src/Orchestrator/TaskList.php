<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

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
        $this->collect($root, $options, []);
    }

    /**
     * @param string[] $groups
     */
    private function collect(TaskNode $node, InputInterface $options, array $groups): void
    {
        $id = $node->getId();
        if (isset($this->nodes[$id])) {
            return;
        }
        $this->nodes[$id] = $node;
        foreach ($groups as $groupId) {
            $this->groupMembers[$groupId][] = $id;
        }
        if ($node instanceof TaskGroupNode) {
            $node->getChildren($this->builder, $options);
            foreach ($this->builder->consume() as $child) {
                $this->collect($child, $options, [...$groups, $id]);
            }
        }
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

    public function isMemberOf(string $groupId, string $id): bool
    {
        return in_array($id, $this->groupMembers[$groupId] ?? [], true);
    }
}
