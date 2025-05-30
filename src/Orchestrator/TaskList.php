<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Tree\TaskNode;

final class TaskList
{
    private TaskNode $root;

    /** @var array<string, TaskNode> */
    private array $nodes = [];

    public function __construct(TaskNode $root)
    {
        $this->root = $root;
        $this->nodes = $this->flatten($root);
    }

    public function getRoot(): TaskNode
    {
        return $this->root;
    }

    /**
     * @return array<string, TaskNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return array<string, TaskNode>
     */
    private function flatten(TaskNode $root): array
    {
        $nodes = [];
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node->isLeaf()) {
                $nodes[$node->id] = $node;
            } else {
                foreach ($node->children as $child) {
                    $stack[] = $child;
                }
            }
        }

        return $nodes;
    }
}
