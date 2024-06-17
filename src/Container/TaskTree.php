<?php

declare(strict_types=1);

namespace Multitron\Container;

use Closure;
use Generator;
use Multitron\Container\Node\PartitionedTaskGroupNode;
use Multitron\Container\Node\TaskGroupNode;
use Multitron\Container\Node\TaskLeafNode;
use Psr\Container\ContainerInterface;

abstract class TaskTree extends TaskGroupNode
{
    public function __construct(protected readonly ContainerInterface $container, ?string $id = null)
    {
        parent::__construct($id ?? 'root', fn() => yield from $this->build());
    }

    protected function task(string $class, ?string $id = null): TaskLeafNode
    {
        return new TaskLeafNode($this->name($id, $class), fn() => $this->container->get($class));
    }

    protected function group(string $id, Closure $factory): TaskGroupNode
    {
        return new TaskGroupNode($id, $factory);
    }

    protected function partitioned(string $class, int $chunks, ?string $id = null): TaskGroupNode
    {
        return new PartitionedTaskGroupNode($this->name($id, $class), fn() => $this->container->get($class), $chunks);
    }

    protected function name(?string $id, string $class): string
    {
        return $id ?? substr(strrchr($class, '\\'), 1);
    }

    abstract public function build(): Generator;

    final public function getDependencies(): array
    {
        return [];
    }

    final public function getGroups(): array
    {
        return [];
    }
}
