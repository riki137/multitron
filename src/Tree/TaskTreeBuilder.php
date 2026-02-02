<?php
declare(strict_types=1);

namespace Multitron\Tree;

use Closure;
use LogicException;
use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Execution\Task;
use Multitron\Tree\Partition\PartitionedTaskInterface;
use Psr\Container\ContainerInterface;

/**
 * Builder for constructing task trees.
 */
final readonly class TaskTreeBuilder
{
    public function __construct(private ?ContainerInterface $container = null, private ?TaskNodeFactory $nodeFactory = null)
    {
    }

    /**
     * Create a simple task node.
     *
     * @param string $id Unique identifier for the task node.
     * @param Closure(): Task $factory Factory that returns the Task instance.
     * @param array<TaskNode|string> $dependencies List of task IDs this node depends on.
     * @param string[] $tags Tags associated with this task.
     */
    public function task(string $id, Closure $factory, array $dependencies = [], array $tags = [], ?string $class = null): TaskNode
    {
        return $this->nodeFactory?->create($id, $factory, [], $dependencies, $tags, $class)
            ?? new TaskNode($id, $factory, [], $dependencies, $tags);
    }

    /**
     * Create a service-backed task node.
     *
     * @param class-string $class FQCN of the service to fetch from container.
     * @param array<TaskNode|string> $dependencies Dependencies for the service task.
     * @param string|null $id Optional identifier for the task. Defaults to short class name.
     * @param string[] $tags Tags associated with this task.
     */
    public function service(string $class, array $dependencies = [], ?string $id = null, array $tags = []): TaskNode
    {
        if ($this->container === null) {
            throw new LogicException('Cannot create service task: TaskTreeBuilderFactory has no container injected.' .
                ' Make sure an instance of ' . ContainerInterface::class . ' is autowired in your DI container OR is passed to ' .
                MultitronFactory::class . ' constructor.');
        }
        return $this->task($id ?? $this->shortClassName($class), fn() => $this->getTask($class), $dependencies, $tags, $class);
    }

    /** Fetch a task service from the container and ensure it implements Task. */
    private function getTask(string $class): Task
    {
        if ($this->container === null) {
            throw new LogicException('Cannot create service task: TaskTreeBuilderFactory has no container injected.' .
                ' Make sure an instance of ' . ContainerInterface::class . ' is autowired in your DI container OR is passed to ' .
                MultitronFactory::class . ' constructor.');
        }
        $task = $this->container->get($class);
        if (!$task instanceof Task) {
            $type = get_debug_type($task);
            throw new LogicException("Service \"{$class}\" must implement Task interface, \"{$type}\" given");
        }
        return $task;
    }

    /**
     * Like {@see getTask()} but ensures the result implements PartitionedTaskInterface.
     */
    private function getPartitionedTask(string $class): PartitionedTaskInterface
    {
        $task = $this->getTask($class);
        if (!$task instanceof PartitionedTaskInterface) {
            $type = get_debug_type($task);
            throw new LogicException("Service \"{$class}\" must implement PartitionedTaskInterface, \"{$type}\" given");
        }
        return $task;
    }

    /**
     * Create a grouping node to hold child tasks.
     *
     * @param string $id Group identifier.
     * @param TaskNode[] $children Child task nodes.
     * @param array<TaskNode|string> $dependencies Dependencies for the group.
     * @param string[] $tags Tags associated with this group.
     */
    public function group(string $id, array $children, array $dependencies = [], array $tags = []): TaskNode
    {
        return $this->nodeFactory?->create($id, null, $children, $dependencies, $tags)
            ?? new TaskNode($id, null, $children, $dependencies, $tags);
    }

    /**
     * Create partitioned tasks for a partitionable service.
     *
     * @param class-string $class FQCN implementing PartitionedTaskInterface.
     * @param int $partitionCount Number of partitions.
     * @param array<TaskNode|string> $dependencies Dependencies for the partitioned tasks.
     * @param string|null $id Optional base identifier for the partitioned tasks. Defaults to short class name.
     * @param string[] $tags Tags associated with the partitioned tasks.
     */
    public function partitioned(
        string $class,
        int $partitionCount,
        array $dependencies = [],
        ?string $id = null,
        array $tags = []
    ): TaskNode {
        return $this->partitionedClosure(
            $id ?? $class,
            fn(): PartitionedTaskInterface => $this->getPartitionedTask($class),
            $partitionCount,
            $dependencies,
            $tags,
            $class
        );
    }

    /**
     * Create partitioned tasks with a custom factory.
     *
     * @param string $id Base identifier for partitions.
     * @param Closure(): (PartitionedTaskInterface|Task) $factory Factory for creating each partition.
     * @param int $partitionCount Number of partitions.
     * @param array<TaskNode|string> $dependencies Dependencies for partitioned tasks.
     * @param string[] $tags Tags associated with the partitioned tasks.
     */
    public function partitionedClosure(
        string $id,
        Closure $factory,
        int $partitionCount,
        array $dependencies = [],
        array $tags = [],
        ?string $class = null
    ): TaskNode {
        $shortId = $this->shortClassName($id);
        $children = [];

        for ($i = 0; $i < $partitionCount; $i++) {
            $label = sprintf('%s %d/%d', $shortId, $i + 1, $partitionCount);

            $children[] = $this->task(
                $label,
                function () use ($factory, $i, $partitionCount): Task {
                    $task = $factory();

                    if (!$task instanceof PartitionedTaskInterface) {
                        $type = get_debug_type($task);
                        throw new LogicException("Expected PartitionedTaskInterface, got {$type}");
                    }

                    $task->setPartitioning($i, $partitionCount);
                    return $task;
                },
                tags: $tags,
                class: $class
            );
        }

        return $this->group($shortId, $children, $dependencies, $tags);
    }

    /**
     * @param string $id Unique identifier for the pattern filter node.
     * @param string $pattern Comma-separated fnmatch patterns to match task IDs or tags.
     * @param TaskNode[] $children Child task nodes to filter.
     * @param string[] $tags Tags associated with this filter node.
     * @param bool $includeDependencies When true (default), include transitive dependencies of matched tasks.
     *                                 When false, only matched tasks are returned and dependency edges pointing
     *                                 outside the matched set are removed.
     */
    public function patternFilter(
        string $id,
        string $pattern,
        array $children = [],
        array $tags = [],
        bool $includeDependencies = true
    ): TaskNode {
        return PatternTaskNodeFactory::create($id, $pattern, $children, $tags, $includeDependencies);
    }

    /**
     * Return the class name without namespace segments. Used when generating
     * default identifiers for tasks based on their service class name.
     */
    private function shortClassName(string $fqcn): string
    {
        $segments = explode('\\', $fqcn);
        return (string)end($segments);
    }
}
