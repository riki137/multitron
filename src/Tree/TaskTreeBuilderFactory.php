<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Psr\Container\ContainerInterface;

final readonly class TaskTreeBuilderFactory
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function create(): TaskTreeBuilder
    {
        return new TaskTreeBuilder($this->container);
    }
}
