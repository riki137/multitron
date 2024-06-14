<?php

declare(strict_types=1);

namespace Multitron\Impl;

use Generator;
use Multitron\Comms\TaskCommunicator;

/**
 * @template T
 */
abstract class ChunkedTask implements Task
{
    abstract protected function count(): int;

    /**
     * @return Generator<iterable<T>>
     */
    abstract protected function fetchAll(): Generator;

    /**
     * @param iterable<T> $chunk
     * @return void
     */
    abstract protected function processChunk(iterable $chunk): void;

    public function execute(TaskCommunicator $comm): void
    {
        $comm->sendProgress($this->count(), 0);
        $i = 0;

        foreach ($this->fetchAll() as $chunk) {
            $count = count($chunk);
            if ($count === 0) {
                continue;
            }
            $i += $count;
            $this->processChunk($chunk);
            $comm->sendProgress($this->count(), $i);
        }
        $comm->sendProgress($this->count(), $this->count());
    }
}
