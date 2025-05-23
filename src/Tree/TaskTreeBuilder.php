<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;
use LogicException;
use Multitron\Execution\Task;
use Multitron\Tree\Partition\PartitionedTaskInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;

final class TaskTreeBuilder
{
    /** @var array<string, TaskNode> */
    private array $nodes = [];

    public function __construct(private readonly ContainerInterface $container)
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
    /**
     * @param Closure(InputInterface): Task $factory
     * @param array<string|TaskNode> $dependencies
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
        return $this->closure($this->getShortClassName($class), fn() => $this->fetchTask($class), $dependencies);
    }

    /**
     * @param string[]|TaskNode[] $dependencies
     */
    public function partitioned(string $class, int $partitionCount, array $dependencies = []): PartitionedTaskGroupNode
    {
        return $this->partitionedClosure($this->getShortClassName($class), $partitionCount, fn() => $this->fetchPartitionedTask($class), $dependencies);
    }

    /**
     * @param Closure(InputInterface): PartitionedTaskInterface $factory
     * @param array<string|TaskNode> $dependencies
     */
    public function partitionedClosure(string $id, int $partitionCount, Closure $factory, array $dependencies = []): PartitionedTaskGroupNode
    {
        return $this->node(new PartitionedTaskGroupNode($id, $partitionCount, $factory, $dependencies));
    }

    /**
     * @param TaskNode[] $children
     * @param array<string|TaskNode> $dependencies
     */
    public function group(string $id, array $children, array $dependencies = []): SimpleTaskGroupNode
    {
        return $this->node(new SimpleTaskGroupNode($id, $children, $dependencies));
    }

    private function fetchTask(string $class): Task
    {
        $task = $this->container->get($class);
        if (!$task instanceof Task) {
            throw new LogicException('Service ' . $class . ' is not a Task');
        }
        return $task;
    }

    private function fetchPartitionedTask(string $class): PartitionedTaskInterface
    {
        $task = $this->container->get($class);
        if (!$task instanceof PartitionedTaskInterface) {
            throw new LogicException('Service ' . $class . ' is not a PartitionedTask');
        }
        return $task;
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
