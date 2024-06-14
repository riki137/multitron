<?php

declare(strict_types=1);

namespace Multitron\Impl;

use Multitron\Container\Def\DirectTaskDefinition;
use Multitron\Container\Def\TaskDefinition;
use Multitron\Container\TaskContainer;

final class PartitionTask implements TaskGroup
{
    public function __construct(
        private readonly string $id,
        private readonly PartitionedTask $mainTask,
        private readonly int $chunks
    ) {
    }

    public function getTasks(): iterable
    {
        $chunks = max(1, $this->chunks);
        for ($i = 0; $i < $chunks; $i++) {
            $id = ($i + 1) . "/$this->chunks";
            yield $this->getTask($this->id, $id);
        }
    }

    public function getTask(string $index): TaskDefinition
    {
        $task = clone $this->mainTask;
        $task->setPartitioning((int)$index - 1, $this->chunks);
        return new DirectTaskDefinition($this->id . TaskContainer::SEP . $index, $task, []);
    }
}
