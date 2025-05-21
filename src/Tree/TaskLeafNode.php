<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Multitron\Execution\Task;
use Symfony\Component\Console\Input\InputInterface;

interface TaskLeafNode extends TaskNode
{
    /**
     * @return callable(): Task
     */
    public function getFactory(InputInterface $options): callable;
}
