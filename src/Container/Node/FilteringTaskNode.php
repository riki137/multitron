<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Generator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * FilteringTaskNode decorates a TaskNode to filter and process nodes based on pattern matching.
 *
 * This class supports filtering nodes using glob patterns, including negative patterns (prefixed with '!').
 * It handles both direct ID matches and group matches, while maintaining dependency relationships.
 */
class FilteringTaskNode extends DecoratorNode
{
    /**
     * @var string[] Array of patterns used for filtering
     */
    private array $patterns;

    /**
     * @param TaskNode $node The node to decorate
     * @param string $pattern Comma-separated patterns (supports glob-style wildcards and negation with !)
     * @throws InvalidArgumentException If pattern is empty or invalid
     */
    public function __construct(TaskNode $node, string $pattern)
    {
        if (empty(trim($pattern))) {
            throw new InvalidArgumentException('Pattern cannot be empty');
        }

        parent::__construct($node);
        $this->patterns = array_filter(
            explode(',', str_replace('%', '*', $pattern)),
            fn($p) => !empty(trim($p))
        );
    }

    /**
     * Processes and filters nodes based on the configured patterns.
     *
     * @return Generator<TaskNode>
     */
    public function getProcessedNodes(): Generator
    {
        try {
            $nodes = [];
            /** @var array<string, array<TaskNode>> $groupsToNodes */
            $groupsToNodes = [];

            // First pass: collect matching nodes and build group mappings
            foreach (parent::getProcessedNodes() as $node) {
                if (!$this->matches($node)) {
                    continue;
                }

                $nodeId = $node->getId();
                if ($node instanceof TaskNodeLeaf && $nodeId !== null) {
                    $nodes[$nodeId] = $node;
                } else {
                    $nodes[] = $node;
                }

                foreach ($node->getGroups() as $group) {
                    $groupsToNodes[$group][] = $node;
                }
            }

            // Second pass: process dependencies
            foreach ($nodes as $node) {
                $this->processDependencies($node, $nodes, $groupsToNodes);
                yield $node;
            }
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Error processing nodes: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Processes dependencies for a given node.
     *
     * @param TaskNode $node
     * @param array<string|int, TaskNode> $nodes
     * @param array<string, array<TaskNode>> $groupsToNodes
     */
    private function processDependencies(TaskNode $node, array $nodes, array $groupsToNodes): void
    {
        foreach ($node->getDependencies() as $dep) {
            if (isset($groupsToNodes[$dep])) {
                $node->doesNotDependOn($dep);
                if ($node instanceof TaskNodeLeaf) {
                    foreach ($groupsToNodes[$dep] as $groupLeaf) {
                        $groupLeaf->dependsOn($node->getId());
                    }
                }
            } elseif (!isset($nodes[$dep])) {
                $node->doesNotDependOn($dep);
            }
        }
    }

    /**
     * Checks if a node matches any of the configured patterns.
     *
     * @param TaskNode $leaf
     * @return bool
     */
    private function matches(TaskNode $leaf): bool
    {
        $matched = false;

        foreach ($this->patterns as $pattern) {
            $pattern = trim($pattern);
            $isNegative = str_starts_with($pattern, '!');
            $actualPattern = $isNegative ? substr($pattern, 1) : $pattern;

            if (empty($actualPattern)) {
                continue;
            }

            $nodeId = $leaf->getId();
            if ($nodeId !== null && fnmatch($actualPattern, $nodeId)) {
                if ($isNegative) {
                    return false;
                }
                $matched = true;
            }

            foreach ($leaf->getGroups() as $group) {
                if (fnmatch($actualPattern, $group)) {
                    if ($isNegative) {
                        return false;
                    }
                    $matched = true;
                }
            }
        }

        return $matched;
    }
}
