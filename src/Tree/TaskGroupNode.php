<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Symfony\Component\Console\Input\InputInterface;

interface TaskGroupNode extends TaskNode
{
    public function getChildren(TaskTreeBuilder $builder, InputInterface $options): void;
}
