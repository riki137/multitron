<?php

declare(strict_types=1);

namespace Multitron\Tree;

interface TaskFilterNode extends TaskGroupNode
{
    public function filter(iterable $leaves): iterable;
}
