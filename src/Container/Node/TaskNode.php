<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Generator;
use LogicException;
use Multitron\Console\InputConfiguration;

/**
 * Abstract base class for task nodes in a dependency graph.
 *
 * TaskNode represents a node that can have dependencies and belong to groups.
 * It supports hierarchical structure through the getNodes() method.
 */
abstract class TaskNode
{
    /** @var array<string, string> */
    private array $dependencies = [];

    /** @var array<string, string> */
    private array $groups = [];

    public function __construct(
        private readonly ?string $id = null
    ) {
        if ($id !== null && trim($id) === '') {
            throw new LogicException('Task ID cannot be empty');
        }
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Configures the task node with input configuration.
     */
    public function configure(InputConfiguration $input): void
    {
    }

    /**
     * Returns child nodes of this task node.
     *
     * @return iterable<TaskNode>
     */
    protected function getNodes(): iterable
    {
        return [];
    }

    /**
     * Returns all processed nodes in the hierarchy.
     *
     * @return Generator<TaskNode>
     * @throws LogicException If there are invalid node configurations
     */
    public function getProcessedNodes(): Generator
    {
        if ($this->id !== null) {
            yield $this;
        }

        foreach ($this->getNodes() as $node) {
            if (!$node instanceof TaskNode) {
                throw new LogicException('Invalid node type returned from getNodes()');
            }

            if ($this->id !== null) {
                $node->belongsTo($this->id);
            }
            $node->dependsOn(...$this->getDependencies());
            yield from $node->getProcessedNodes();
        }
    }

    /**
     * Adds this node to a group.
     *
     * @param string|TaskNode $group The group identifier or node
     * @throws LogicException If the group node has no ID
     */
    final public function belongsTo(string|TaskNode $group): static
    {
        if ($group instanceof TaskNode) {
            if ($group->id === null) {
                throw new LogicException('TaskNode that is used as a group must have an id (group or leaf)');
            }
            $group = $group->getId();
        }
        $this->groups[$group] = $group;
        return $this;
    }

    /**
     * Removes this node from a group.
     *
     * @param string|TaskNode $group The group identifier or node
     * @throws LogicException If the group node has no ID
     */
    final public function doesNotBelongTo(string|TaskNode $group): static
    {
        if ($group instanceof TaskNode) {
            if ($group->id === null) {
                throw new LogicException('TaskNode that is used as a group must have an id (group or leaf)');
            }
            $group = $group->getId();
        }
        unset($this->groups[$group]);
        return $this;
    }

    /**
     * Returns all groups this node belongs to.
     *
     * @return array<string, string>
     */
    final public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Checks if this node belongs to a specific group.
     *
     * @param string|TaskNode $group The group identifier or node
     */
    final public function hasGroup(string|TaskNode $group): bool
    {
        if ($group instanceof TaskNode) {
            $group = $group->getId();
        }
        return isset($this->groups[$group]);
    }

    /**
     * Adds dependencies to this node.
     *
     * @param string|TaskNode ...$dependencies The dependencies to add
     * @throws LogicException If any dependency node has no ID
     */
    final public function dependsOn(string|TaskNode ...$dependencies): static
    {
        foreach ($dependencies as $dependency) {
            if ($dependency instanceof TaskNode) {
                if ($dependency->id === null) {
                    throw new LogicException('TaskNode that is used as a dependency must have an id (group or leaf)');
                }
                $dependency = $dependency->getId();
            }
            $this->dependencies[$dependency] = $dependency;
        }
        return $this;
    }

    /**
     * Removes a dependency from this node.
     *
     * @param string|TaskNode $dependency The dependency to remove
     * @throws LogicException If the dependency node has no ID
     */
    final public function doesNotDependOn(string|TaskNode $dependency): static
    {
        if ($dependency instanceof TaskNode) {
            if ($dependency->id === null) {
                throw new LogicException('TaskNode that is used as a dependency must have an id (group or leaf)');
            }
            $dependency = $dependency->getId();
        }
        unset($this->dependencies[$dependency]);
        return $this;
    }

    /**
     * Returns all dependencies of this node.
     *
     * @return array<string, string>
     */
    final public function getDependencies(): array
    {
        return $this->dependencies;
    }
}
