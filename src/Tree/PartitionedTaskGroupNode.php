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
     * @param Closure(InputInterface): PartitionedTaskInterface $factory
     * @param (string|TaskNode)[] $dependencies
     */
    public function __construct(
        string $id,
        private readonly int $partitionCount,
        private readonly Closure $factory,
        array $dependencies
    ) {
        parent::__construct($id, $dependencies);
    }

    public function getChildren(TaskTreeBuilder $builder, InputInterface $options): void
    {
        for ($i = 0; $i < $this->partitionCount; $i++) {
            $builder->closure(
                $this->getId() . ' ' . ($i + 1) . '/' . $this->partitionCount,
                fn(InputInterface $input): PartitionedTaskInterface => $this->createPartition($input, $i)
            );
        }
    }

    private function createPartition(InputInterface $input, int $index): PartitionedTaskInterface
    {
        $task = ($this->factory)($input);
        $task->setPartitioning($index, $this->partitionCount);
        return $task;
    }
}
