<?php
declare(strict_types=1);

namespace Multitron\Impl;

use Generator;
use Multitron\Comms\Data\IterationStatus;
use Multitron\Comms\Data\Message\ProgressMessage;
use Multitron\Comms\Data\TaskInfo;
use Multitron\Comms\TaskCommunicator;

abstract class ForeachTask implements Task
{
    public function __construct(private readonly TaskInfo $info)
    {
    }

    public function getInfo(): TaskInfo
    {
        return $this->info;
    }

    public function execute(TaskCommunicator $comm): void
    {
        $progress = new ProgressMessage($this->count());
        $comm->sendMessage($progress);
        foreach ($this->fetchAll() as $item) {
            $status = $this->process($item, $comm);
            match ($status) {
                IterationStatus::DONE => $progress->done++,
                IterationStatus::ERROR => $progress->error++,
                IterationStatus::WARNING => $progress->warning++,
                IterationStatus::SKIPPED => $progress->skipped++,
            };
            $comm->sendMessage($progress);
        }
    }

    protected function count(): int
    {
        return 0;
    }

    abstract protected function fetchAll(): Generator;

    abstract protected function process(mixed $item, TaskCommunicator $comm): IterationStatus;
}
