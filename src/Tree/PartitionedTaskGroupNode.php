<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;
use Multitron\Execution\Task;
use Multitron\Tree\Partition\PartitionedTaskInterface;
use Symfony\Component\Console\Input\InputInterface;

final class PartitionedTaskGroupNode extends AbstractTaskGroupNode
{
    /**
     * @param string $id
     * @param int $partitionCount
     * @param Closure(): PartitionedTaskInterface $factory
     * @param (string|TaskNode)[] $dependencies
     */
    public function __construct(
        private readonly string $id,
        private readonly int $partitionCount,
        private readonly Closure $factory,
        array $dependencies
    ) {
        parent::__construct($id, $dependencies);
    }

    public function getChildren(TaskTreeBuilder $builder, InputInterface $options): void
    {
        for ($i = 0; $i < $this->partitionCount; $i++) {
            $builder->closure($this->id . ' ' . ($i + 1) . '/' . $this->partitionCount, fn() => $this->createPartition($i));
        }
    }

    private function createPartition(int $index): Task
    {
        $task = ($this->factory)();
        $task->setPartitioning($index, $this->partitionCount);
        return $task;
    }
}
