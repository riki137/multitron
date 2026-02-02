<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;
use Multitron\Execution\Task;

/**
 * Represents a node in the task tree before compilation.
 */
final readonly class TaskNode
{
    /**
     * @internal
     * @param ?Closure(): Task $factory Creates the task instance.
     * @param array<TaskNode> $children Child task nodes.
     * @param array<TaskNode|string> $dependencies IDs or TaskNode objects this node depends on.
     * @param array<string> $tags Tags used for filtering or post-processing.
     * @param ?Closure(array<CompiledTaskNode> $tasks): iterable<CompiledTaskNode> $postProcess Custom hook for post-processing compiled tasks.
     */
    public function __construct(
        public string $id,
        public ?Closure $factory = null,
        public array $children = [],
        public array $dependencies = [],
        public array $tags = [],
        public ?Closure $postProcess = null,
    ) {
    }

    /**
     * Returns a new instance with the specified properties replaced.
     *
     * @param array<TaskNode>|null $children
     * @param array<TaskNode|string>|null $dependencies
     * @param array<string>|null $tags
     */
    public function with(
        ?Closure $factory = null,
        ?array $children = null,
        ?array $dependencies = null,
        ?array $tags = null,
        ?Closure $postProcess = null,
    ): self {
        return new TaskNode(
            id: $this->id,
            factory: $factory ?? $this->factory,
            children: $children ?? $this->children,
            dependencies: $dependencies ?? $this->dependencies,
            tags: $tags ?? $this->tags,
            postProcess: $postProcess ?? $this->postProcess,
        );
    }

    /**
     * Returns a new instance with additional children, dependencies, and tags.
     *
     * @param array<TaskNode> $children
     * @param array<TaskNode|string> $dependencies
     * @param array<string> $tags
     */
    public function withAdded(
        array $children = [],
        array $dependencies = [],
        array $tags = [],
    ): self {
        return $this->with(
            children: array_merge($this->children, $children),
            dependencies: array_merge($this->dependencies, $dependencies),
            tags: array_merge($this->tags, $tags),
        );
    }

    /**
     * Returns a new instance with the specified properties removed or reset.
     *
     * @param bool|array<TaskNode> $children Pass true to clear, or an array of nodes to remove.
     * @param bool|array<TaskNode|string> $dependencies Pass true to clear, or an array of items to remove.
     * @param bool|array<string> $tags Pass true to clear, or an array of tags to remove.
     */
    public function without(
        bool $factory = false,
        bool|array $children = false,
        bool|array $dependencies = false,
        bool|array $tags = false,
        bool $postProcess = false,
    ): self {
        return new TaskNode(
            id: $this->id,
            factory: $factory ? null : $this->factory,
            children: $this->filterProperty($children, $this->children),
            dependencies: $this->filterProperty($dependencies, $this->dependencies),
            tags: $this->filterProperty($tags, $this->tags),
            postProcess: $postProcess ? null : $this->postProcess,
        );
    }

    /**
     * Internal helper to handle the removal or clearing of collection properties.
     *
     * @template T
     * @param bool|array<T> $property
     * @param array<T> $current
     * @return array<T>
     */
    private function filterProperty(bool|array $property, array $current): array
    {
        if ($property === false) {
            return $current;
        }

        if (is_array($property)) {
            return array_values(array_filter($current, fn($item) => !in_array($item, $property, true)));
        }

        return [];
    }

    /**
     * Whether the node has no children.
     */
    public function isLeaf(): bool
    {
        return [] === $this->children;
    }
}
