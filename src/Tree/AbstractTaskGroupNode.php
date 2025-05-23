<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Symfony\Component\Console\Input\InputInterface;

abstract class AbstractTaskGroupNode implements TaskGroupNode
{
    private readonly array $dependencies;

    /**
     * @param string[]|TaskNode[] $dependencies
     */
    public function __construct(private readonly string $id, array $dependencies = [])
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
}
