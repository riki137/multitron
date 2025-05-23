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

    private TaskTreeBuilder $builder;
    private TaskTreeBuilderFactory $factory;

    public function __construct(TaskTreeBuilderFactory $factory, TaskNode $root, InputInterface $options)
    {
        $this->factory = $factory;
        $this->builder = $factory->create();
        $this->collect($root, $options);
    }

    private function collect(TaskNode $node, InputInterface $options): void
    {
        $id = $node->getId();
        if (isset($this->nodes[$id])) {
            return;
        }
        $this->nodes[$id] = $node;
        if ($node instanceof TaskGroupNode) {
            $node->getChildren($this->builder, $options);
            foreach ($this->builder->consume() as $child) {
                $this->collect($child, $options);
            }
        }
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }
}
