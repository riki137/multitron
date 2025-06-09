<?php

declare(strict_types=1);

namespace Multitron\Tree;

class PatternTaskNodeFactory
{
    public static function create(
        string $id,
        string $pattern,
        array $children = [],
    ): TaskNode {
        $patterns = array_map(fn($p) => strtr($p, ['%' => '*']), explode(',', $pattern));
        return new TaskNode(
            $id,
            children: $children,
            postProcess: function (array $tasks) use ($patterns, $pattern): iterable {
                /** @var CompiledTaskNode $task */
                foreach ($tasks as $task) {
                    foreach ($patterns as $pattern) {
                        if (fnmatch($pattern, $task->id)) {
                            yield self::filterDependencies($task, $patterns);
                            continue 2;
                        }
                        foreach ($task->tags as $tag) {
                            if (fnmatch($pattern, $tag)) {
                                yield self::filterDependencies($task, $patterns);
                                continue 3; // Skip to the next task
                            }
                        }
                    }
                }
            }
        );
    }

    private static function filterDependencies(CompiledTaskNode $task, array $patterns): CompiledTaskNode
    {
        if ($task->dependencies === []) {
            return $task; // No dependencies to filter
        }
        $dependencies = array_filter($task->dependencies, function ($dependency) use ($patterns) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $dependency)) {
                    return true;
                }
            }
            return false;
        });
        if ($dependencies === $task->dependencies) {
            return $task; // No filtering needed
        }
        return new CompiledTaskNode(
            $task->id,
            $task->factory,
            $dependencies,
            $task->tags
        );
    }
}
