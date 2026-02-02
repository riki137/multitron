<?php

declare(strict_types=1);

namespace Multitron\Tree;

class PatternTaskNodeFactory
{
    /**
     * @param string $id The ID of the task node.
     * @param string $pattern Comma-separated list of fnmatch patterns to match task IDs or tags
     * @param TaskNode[] $children Child task nodes to filter.
     * @param string[] $tags Tags associated with this filter node.
     * @param bool $includeDependencies When true (default), include transitive dependencies of matched tasks.
     *                                 When false, only matched tasks are returned and dependency edges pointing
     *                                 outside the matched set are removed.
     */
    public static function create(
        string $id,
        string $pattern,
        array $children = [],
        array $tags = [],
        bool $includeDependencies = true,
    ): TaskNode {
        $patterns = array_values(array_filter(array_map(
            static fn(string $p): string => strtr(trim($p), ['%' => '*']),
            explode(',', $pattern)
        ), static fn(string $p): bool => $p !== ''));

        return new TaskNode(
            $id,
            children: $children,
            tags: $tags,
            postProcess: function (array $tasks) use ($patterns, $includeDependencies): iterable {
                $selected = [];

                foreach ($tasks as $task) {
                    if (self::matches($task, $patterns)) {
                        $selected[$task->id] = $task;
                    }
                }

                if ($includeDependencies) {
                    $queue = array_values($selected);
                    while ($queue) {
                        $current = array_pop($queue);
                        foreach ($current->dependencies as $dep) {
                            if (!isset($selected[$dep]) && isset($tasks[$dep])) {
                                $selected[$dep] = $tasks[$dep];
                                $queue[] = $tasks[$dep];
                            }
                        }
                    }

                    foreach ($selected as $task) {
                        yield $task;
                    }

                    return;
                }

                foreach ($selected as $task) {
                    $filteredDeps = array_values(array_filter(
                        $task->dependencies,
                        static fn(string $depId): bool => isset($selected[$depId])
                    ));

                    yield new CompiledTaskNode(
                        id: $task->id,
                        factory: $task->factory,
                        dependencies: $filteredDeps,
                        tags: $task->tags
                    );
                }

                return;
            }
        );
    }

    /**
     * Check if a task matches any of the given patterns by ID or tags.
     * @param CompiledTaskNode $task The task to check.
     * @param string[] $patterns Array of fnmatch patterns
     */
    private static function matches(CompiledTaskNode $task, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $task->id)) {
                return true;
            }
            foreach ($task->tags as $tag) {
                if (fnmatch($pattern, $tag)) {
                    return true;
                }
            }
        }

        return false;
    }
}
