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
     */
    public static function create(
        string $id,
        string $pattern,
        array $children = [],
        array $tags = [],
    ): TaskNode {
        $patterns = array_map(fn($p) => strtr($p, ['%' => '*']), explode(',', $pattern));
        return new TaskNode(
            $id,
            children: $children,
            tags: $tags,
            postProcess: function (array $tasks) use ($patterns): iterable {
                $selected = [];

                foreach ($tasks as $task) {
                    if (self::matches($task, $patterns)) {
                        $selected[$task->id] = $task;
                    }
                }

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
