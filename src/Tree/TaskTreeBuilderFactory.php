<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Psr\Container\ContainerInterface;

final class TaskTreeBuilderFactory
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function create(): TaskTreeBuilder
    {
        return new TaskTreeBuilder($this->container);
    }
}
