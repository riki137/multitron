<?php
declare(strict_types=1);

namespace Multitron\Tree;

use Closure;
use LogicException;
use Multitron\Execution\Task;
use Multitron\Tree\Partition\PartitionedTaskInterface;
use Psr\Container\ContainerInterface;

/**
 * Builder for constructing task trees.
 */
final readonly class TaskTreeBuilder
{
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * Create a simple task node.
     *
     * @param string $id Unique identifier for the task node.
     * @param Closure(): Task $factory Factory that returns the Task instance.
     * @param string[] $dependencies List of task IDs this node depends on.
     */
    public function task(string $id, Closure $factory, array $dependencies = []): TaskNode
    {
        return new TaskNode($id, $factory, [], $dependencies);
    }

    /**
     * Create a service-backed task node.
     *
     * @param class-string $class FQCN of the service to fetch from container.
     * @param string[] $dependencies Dependencies for the service task.
     */
    public function service(string $class, array $dependencies = []): TaskNode
    {
        $id = $this->shortClassName($class);
        $factory = fn(): Task => $this->container->get($class);

        return $this->task($id, $factory, $dependencies);
    }

    /**
     * Create a grouping node to hold child tasks.
     *
     * @param string $id Group identifier.
     * @param TaskNode[] $children Child task nodes.
     * @param string[] $dependencies Dependencies for the group.
     */
    public function group(string $id, array $children, array $dependencies = []): TaskNode
    {
        return new TaskNode($id, null, $children, $dependencies);
    }

    /**
     * Create partitioned tasks for a partitionable service.
     *
     * @param class-string $class FQCN implementing PartitionedTaskInterface.
     * @param int $partitionCount Number of partitions.
     * @param string[] $dependencies Dependencies for the partitioned tasks.
     */
    public function partitioned(string $class, int $partitionCount, array $dependencies = []): TaskNode
    {
        return $this->partitionedClosure(
            $class,
            fn(): PartitionedTaskInterface => $this->container->get($class),
            $partitionCount,
            $dependencies
        );
    }

    /**
     * Create partitioned tasks with a custom factory.
     *
     * @param string $id Base identifier for partitions.
     * @param Closure(): PartitionedTaskInterface $factory Factory for creating each partition.
     * @param int $partitionCount Number of partitions.
     * @param string[] $dependencies Dependencies for partitioned tasks.
     */
    public function partitionedClosure(
        string $id,
        Closure $factory,
        int $partitionCount,
        array $dependencies = []
    ): TaskNode {
        $shortId = $this->shortClassName($id);
        $children = [];

        for ($i = 0; $i < $partitionCount; $i++) {
            $label = sprintf('%s %d/%d', $shortId, $i + 1, $partitionCount);

            $children[] = new TaskNode(
                $label,
                function () use ($factory, $i, $partitionCount): Task {
                    $task = $factory();

                    if (!$task instanceof PartitionedTaskInterface) {
                        $type = get_debug_type($task);
                        throw new LogicException("Expected PartitionedTaskInterface, got {$type}");
                    }

                    $task->setPartitioning($i, $partitionCount);
                    return $task;
                }
            );
        }

        return new TaskNode($shortId, null, $children, $dependencies);
    }

    public function patternFilter(string $id, string $pattern, array $children = []): TaskNode
    {
        return PatternTaskNodeFactory::create($id, $pattern, $children);
    }

    /**
     * Extract the short class name from a FQCN.
     */
    private function shortClassName(string $fqcn): string
    {
        $segments = explode('\\', $fqcn);
        return (string)end($segments);
    }
}
