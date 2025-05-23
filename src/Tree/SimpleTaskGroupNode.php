<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Symfony\Component\Console\Input\InputInterface;

final readonly class SimpleTaskGroupNode implements TaskGroupNode
{
    /** @var string[] */
    private array $dependencies;

    /**
     * @param string $id
     * @param TaskNode[] $children
     * @param (string|TaskNode)[] $dependencies
     */
    /**
     * @param TaskNode[] $children
     * @param array<string|TaskNode> $dependencies
     */
    public function __construct(private string $id, private array $children = [], array $dependencies = [])
    {
        $this->dependencies = ClosureTaskNode::castDependencies($dependencies);
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string[]
     */
    public function getDependencies(InputInterface $options): array
    {
        return $this->dependencies;
    }

    public function getChildren(TaskTreeBuilder $builder, InputInterface $options): void
    {
        foreach ($this->children as $child) {
            $builder->node($child);
        }
    }
}
