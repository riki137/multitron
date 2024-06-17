<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Closure;
use Multitron\Impl\PartitionedTask;
use RuntimeException;

class PartitionedTaskLeafNode extends TaskLeafNode
{
    public function __construct(string $id, Closure $parentFactory, int $index, int $modulo)
    {
        parent::__construct($id, function () use ($modulo, $index, $parentFactory) {
            $task = clone($parentFactory());
            if (!$task instanceof PartitionedTask) {
                throw new RuntimeException(get_class($task) . ' must be an instance of PartitionedTask');
            }
            $task->setPartitioning($index, $modulo);
            return $task;
        });
    }
}
