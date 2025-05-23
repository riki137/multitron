<?php

declare(strict_types=1);

namespace Multitron\Tree;

interface TaskFilterNode extends TaskNode
{
    /**
     * @param TaskNode $node
     * @param array<string, TaskGroupNode> $groups groupId => TaskGroupNode
     * @return bool
     */
    public function filter(TaskNode $node, array $groups): bool;
}
