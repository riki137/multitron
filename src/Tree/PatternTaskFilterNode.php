<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Symfony\Component\Console\Input\InputInterface;

final readonly class PatternTaskFilterNode implements TaskFilterNode
{
    public function __construct(private string $id, private string $pattern)
    {
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
        return [];
    }

}
