<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

class TaskFilteringNode extends TaskGroupNode
{
    private readonly TaskTreeProcessor $tree;

    public function __construct(
        string $id,
        TaskGroupNode $node,
        private readonly string $pattern,
    ) {
        $this->tree = new TaskTreeProcessor($node);
        parent::__construct($id, fn() => [$node]);
    }

    public function getTasks(): iterable
    {
        foreach (parent::getTasks() as $node) {
            $patterns = explode(',', $this->pattern);
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $node->getId())) {
                    assert($node instanceof TaskLeafNode);
                    $node = $this->filterDependencies($node, $patterns);
                    yield $node;
                }
            }
        }
    }

    private function filterDependencies(TaskLeafNode $node, array $patterns): TaskLeafNode
    {
        foreach ($node->getDependencies() as $dep) {
            if ($this->tree->isGroup($dep)) {
                $node->removeDependency($dep);
                foreach ($this->tree->getLeafIdsInGroup($dep) as $leaf) {
                    $node->dependsOn($leaf);
                }
            }
        }

        foreach ($node->getDependencies() as $dep) {
            $matches = false;
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $dep)) {
                    $matches = true;
                    break;
                }
            }
            if (!$matches) {
                $node->removeDependency($dep);
            }
        }
        return $node;
    }
}
