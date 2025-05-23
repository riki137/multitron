<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;
use Multitron\Execution\Task;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;

final class TaskTreeBuilder
{
    private array $nodes = [];

    public function __construct(private ContainerInterface $container)
    {
    }

    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }

    public function addAll(TaskNode ...$nodes): void
    {
        foreach ($nodes as $node) {
            $this->node($node);
        }
    }

    /**
     * @template T of TaskNode
     * @param T&TaskNode $node
     * @return T&TaskNode
     */
    public function node(TaskNode $node): TaskNode
    {
        return $this->nodes[$node->getId()] = $node;
    }

    /**
     * @param string $id
     * @param Closure(InputInterface): Task $factory
     * @param array $dependencies
     * @return ClosureTaskNode
     */
    public function closure(string $id, Closure $factory, array $dependencies = []): ClosureTaskNode
    {
        return $this->node(new ClosureTaskNode($id, $factory, $dependencies));
    }

    /**
     * @param string[]|TaskNode[] $dependencies
     */
    public function service(string $class, array $dependencies = []): ClosureTaskNode
    {
        return $this->closure($this->getShortClassName($class), fn() => $this->container->get($class), $dependencies);
    }

    /**
     * @param string[]|TaskNode[] $dependencies
     */
    public function partitioned(string $class, int $partitionCount, array $dependencies = []): PartitionedTaskGroupNode
    {
        return $this->partitionedClosure($this->getShortClassName($class), $partitionCount, fn() => $this->container->get($class), $dependencies);
    }

    /**
     * @param string[]|TaskNode[] $dependencies
     */
    public function partitionedClosure(string $id, int $partitionCount, Closure $factory, array $dependencies = []): PartitionedTaskGroupNode
    {
        return $this->node(new PartitionedTaskGroupNode($id, $partitionCount, $factory, $dependencies));
    }

    /**
     * @param TaskNode[] $children
     * @param string[]|TaskNode[] $dependencies
     */
    public function group(string $id, array $children, array $dependencies = []): SimpleTaskGroupNode
    {
        return $this->node(new SimpleTaskGroupNode($id, $children, $dependencies));
    }

    /**
     * @return TaskNode[]
     */
    public function consume(): array
    {
        $nodes = $this->nodes;
        $this->nodes = [];
        return $nodes;
    }
}
