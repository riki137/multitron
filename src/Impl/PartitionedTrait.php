<?php
declare(strict_types=1);

namespace Multitron\Impl;

/**
 * @implements PartitionedTask
 */
trait PartitionedTrait
{
    protected int $partitionIndex = 0;

    protected int $partitionModulo = 1;

    public function setPartitioning(int $index, int $modulo): void
    {
        $this->partitionIndex = $index;
        $this->partitionModulo = $modulo;
    }
}
