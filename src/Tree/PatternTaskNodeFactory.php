<?php

declare(strict_types=1);

namespace Multitron\Tree;

class PatternTaskNodeFactory
{
    /**
     * @param TaskNode[] $children
     */
    public static function create(
        string $id,
        string $pattern,
        array $children = [],
    ): TaskNode {
        $patterns = array_map(fn($p) => strtr($p, ['%' => '*']), explode(',', $pattern));
        return new TaskNode(
            $id,
            children: $children,
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
     * @param string[] $patterns
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
