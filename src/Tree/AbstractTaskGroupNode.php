<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Symfony\Component\Console\Input\InputInterface;

abstract class AbstractTaskGroupNode implements TaskNode
{
    private readonly array $dependencies;

    public function __construct(private readonly string $id, array $dependencies = [])
    {
        $this->dependencies = ClosureTaskNode::castDependencies($dependencies);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDependencies(InputInterface $options): array
    {
        return $this->dependencies;
    }

    public function getFactory(InputInterface $options): ?callable
    {
        return null;
    }
}
