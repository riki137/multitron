<?php

declare(strict_types=1);

namespace Multitron\Util;

use RuntimeException;

class CircularDependencyChecker
{
    /**
     * Checks for circular dependencies in the given dependency graph.
     *
     * @param array $dependencyGraph The dependency graph.
     * @throws RuntimeException if a circular dependency is detected.
     */
    public function check(array $dependencyGraph)
    {
        $visited = [];
        $stack = [];

        foreach ($dependencyGraph as $node => $dependencies) {
            if (!isset($visited[$node])) {
                $this->dfs($node, $dependencyGraph, $visited, $stack);
            }
        }
    }

    /**
     * Performs a Depth-First Search to detect cycles in the dependency graph.
     *
     * @param string $node The current node.
     * @param array $dependencyGraph The dependency graph.
     * @param array $visited Array to keep track of visited nodes.
     * @param array $stack Array to keep track of the recursion stack.
     * @throws RuntimeException if a circular dependency is detected.
     */
    private function dfs($node, $dependencyGraph, &$visited, &$stack)
    {
        $visited[$node] = true;
        $stack[$node] = true;

        if (isset($dependencyGraph[$node])) {
            foreach ($dependencyGraph[$node] as $dependency) {
                if (!isset($visited[$dependency])) {
                    $this->dfs($dependency, $dependencyGraph, $visited, $stack);
                } elseif (isset($stack[$dependency])) {
                    $this->throwCircularDependencyException($node, $dependency, $stack);
                }
            }
        }

        unset($stack[$node]);
    }

    /**
     * Throws a RuntimeException with the circular path.
     *
     * @param string $startNode The start node of the cycle.
     * @param string $endNode The end node of the cycle.
     * @param array $stack The recursion stack.
     * @throws RuntimeException
     */
    private function throwCircularDependencyException($startNode, $endNode, $stack)
    {
        $cycle = [];
        $recording = false;

        foreach (array_keys($stack) as $node) {
            if ($node === $endNode) {
                $recording = true;
            }

            if ($recording) {
                $cycle[] = $node;
            }

            if ($node === $startNode) {
                break;
            }
        }

        $cycle[] = $endNode;
        $cyclePath = implode(' -> ', $cycle);

        throw new RuntimeException("Circular dependency detected: $cyclePath");
    }
}
