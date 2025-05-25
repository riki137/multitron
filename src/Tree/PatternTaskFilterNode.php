<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Symfony\Component\Console\Input\InputInterface;
use function fnmatch;

/**
 * Pattern-based task filter that only exposes its children when they match
 * provided glob patterns.
 */
final readonly class PatternTaskFilterNode implements TaskFilterNode, TaskGroupNode
{
    /** @var string[] */
    private array $dependencies;

    /** @var TaskNode[] */
    private array $children;

    /**
     * @param string $id
     * @param string $pattern
     * @param TaskNode[] $children
     * @param array<string|TaskNode> $dependencies
     */
    public function __construct(
        private string $id,
        private string $pattern,
        array $children = [],
        array $dependencies = []
    ) {
        $this->children     = $children;
        $this->dependencies = ClosureTaskNode::castDependencies($dependencies);
    }

    public function filter(TaskNode $node, array $groups): bool
    {
        if (fnmatch($this->pattern, $node->getId())) {
            return true;
        }
        foreach ($groups as $groupId => $group) {
            if (fnmatch($this->pattern, $groupId)) {
                return true;
            }
        }
        return false;
    }

    public function getId(): string
    {
        return $this->id;
    }

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

