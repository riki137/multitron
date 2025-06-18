<?php

declare(strict_types=1);

namespace Multitron\Tree;

use LogicException;

/**
 * Turns a TaskNode tree into a flat list of CompiledTaskNode objects - tasks ready to be executed.
 */
final class TaskTreeCompiler
{
    /**
     * @var array<string, string[]> Map tag → list of node IDs having that tag
     */
    private array $tagIndex = [];

    /**
     * Compiles a TaskNode tree into a flat list of executable tasks.
     *
     * @param TaskNode $root The root node of the task tree.
     * @return array<string, CompiledTaskNode> A flat array of compiled tasks.
     */
    public function compile(TaskNode $root): array
    {
        // Build an index of all tags in the tree → node IDs
        $this->buildTagIndex($root);

        // Start the recursive compilation from the root node
        $result = $this->processNode($root, [], []);

        $this->tagIndex = []; // Clear tag index after compilation

        return $result;
    }

    /**
     * Recursively walk the tree to populate $this->tagIndex.
     *
     * @param string[] $parents
     */
    private function buildTagIndex(TaskNode $node, array $parents = []): void
    {
        foreach ($node->tags as $tag) {
            $this->tagIndex[$tag][] = $node->id;
        }
        foreach ($parents as $parent) {
            $this->tagIndex[$parent][] = $node->id; // Include parent IDs in tag index
        }

        $parents[] = $node->id;
        foreach ($node->children as $child) {
            $this->buildTagIndex($child, $parents);
        }
    }

    /**
     * Recursively processes a node and its children, compiling them into a flat list.
     *
     * @param TaskNode $node
     * @param string[] $parentDependencies
     * @param string[] $parentTags
     * @return CompiledTaskNode[]
     */
    private function processNode(TaskNode $node, array $parentDependencies, array $parentTags): array
    {
        // 1. Combine parent deps + direct deps (expanded for tags)
        $nodeLevelDependencies = array_unique(
            array_merge($parentDependencies, $this->getDirectDependencies($node))
        );

        // 2. Combine parent tags + this node's tags
        $nodeLevelTags = array_unique(array_merge($parentTags, $node->tags));

        $subtreeTasks = [];

        // 3. If this node has a factory, compile it into a task
        if ($node->factory !== null) {
            $subtreeTasks[$node->id] = new CompiledTaskNode(
                id: $node->id,
                factory: $node->factory,
                dependencies: array_values($nodeLevelDependencies),
                tags: array_values($nodeLevelTags)
            );
        }

        // 4. Prepare contexts for children
        $dependenciesForChildren = $nodeLevelDependencies;
        $tagsForChildren = array_unique(array_merge($nodeLevelTags, [$node->id]));

        // 5. Recurse into children
        foreach ($node->children as $childNode) {
            $childTasks = $this->processNode($childNode, $dependenciesForChildren, $tagsForChildren);
            $subtreeTasks = array_merge($subtreeTasks, $childTasks);
        }

        // 6. Apply post-processing if present
        if ($node->postProcess !== null) {
            /** @var iterable<CompiledTaskNode|mixed> $gen */
            $gen = ($node->postProcess)($subtreeTasks);
            $result = [];
            foreach ($gen as $task) {
                if (!$task instanceof CompiledTaskNode) {
                    throw new LogicException(
                        'Post-processing closure must return an iterable of CompiledTaskNode objects, got: '
                        . get_debug_type($task)
                    );
                }
                $result[$task->id] = $task;
            }
            return $result;
        }

        // 7. Return compiled tasks for this subtree
        return $subtreeTasks;
    }

    /**
     * @param TaskNode $node
     * @return string[] A flat list of dependency IDs, expanding tags → node IDs
     */
    public function getDirectDependencies(TaskNode $node): array
    {
        $resolved = [];
        $queue = $node->dependencies;
        $seen = [];

        while (!empty($queue)) {
            /** @var mixed $dep */
            $dep = array_shift($queue);

            if ($dep instanceof TaskNode) {
                $dep = $dep->id;
            }

            if (!is_string($dep)) {
                throw new LogicException(
                    'Dependencies must be either TaskNode instances or strings, got: '
                    . get_debug_type($dep)
                );
            }

            if (isset($seen[$dep])) {
                continue;
            }

            $seen[$dep] = true;

            if (isset($this->tagIndex[$dep])) {
                foreach ($this->tagIndex[$dep] as $nested) {
                    if (!isset($seen[$nested])) {
                        $queue[] = $nested;
                    }
                }
            } else {
                $resolved[] = $dep;
            }
        }

        return $resolved;
    }
}
