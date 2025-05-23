<?php

declare(strict_types=1);

namespace Multitron\Tree;

interface TaskFilterNode extends TaskGroupNode
{
    /**
     * @param iterable<TaskLeafNode> $leaves
     * @return iterable<TaskLeafNode>
     */
    public function filter(iterable $leaves): iterable;
}
