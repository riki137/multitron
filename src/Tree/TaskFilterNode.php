<?php

declare(strict_types=1);

namespace Multitron\Tree;

interface TaskFilterNode extends TaskNode
{
    public function filter(iterable $leaves): iterable;
}
