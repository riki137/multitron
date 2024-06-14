<?php
declare(strict_types=1);

namespace Multitron\Impl;

interface PartitionedTask extends Task
{
    public function setPartitioning(int $index, int $modulo): void;
}
