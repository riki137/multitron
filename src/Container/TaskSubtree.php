<?php

declare(strict_types=1);

namespace Multitron\Container;

use Closure;
use Generator;
use Multitron\Console\InputConfiguration;
use Multitron\Container\Node\ArrayTaskNodeGroup;
use Multitron\Container\Node\ClosureTaskNodeGroup;
use Multitron\Container\Node\ContainerTaskNode;
use Multitron\Container\Node\FactoryTaskNode;
use Multitron\Container\Node\PartitionedTaskNodeGroup;
use Multitron\Container\Node\TaskNode;
use Multitron\Container\Node\TaskNodeGroup;
use Multitron\Container\Node\TaskNodeLeaf;
use Multitron\Impl\Task;
use Psr\Container\ContainerInterface;

abstract class TaskSubtree extends TaskNode
{
    public function __construct(protected readonly ContainerInterface $container, ?string $id = null)
    {
        parent::__construct($this->name($id, static::class));
    }

    protected function task(string $class, ?string $id = null): TaskNodeLeaf
    {
        return new ContainerTaskNode($this->name($id, $class), $this->container, $class);
    }

    /**
     * @param string $id
     * @param Closure(): Task $factory
     * @return TaskNodeLeaf
     */
    protected function factoryTask(string $id, Closure $factory): TaskNodeLeaf
    {
        return new FactoryTaskNode($id, $factory);
    }

    /**
     * @param string $id
     * @param TaskNode[] $nodes
     * @return TaskNode
     */
    protected function group(string $id, array $nodes): TaskNode
    {
        return new ArrayTaskNodeGroup($id, $nodes);
    }

    protected function groupFactory(string $id, Closure $factory): TaskNode
    {
        return new ClosureTaskNodeGroup($id, $factory);
    }

    protected function partitioned(string $class, int $chunks, ?string $id = null): TaskNode
    {
        return new PartitionedTaskNodeGroup($this->name($id, $class), $this->task($class), $chunks);
    }

    protected function name(?string $id, string $class): string
    {
        return $id ?? substr(strrchr($class, '\\') ?: $class, 1);
    }
}
