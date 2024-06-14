<?php

declare(strict_types=1);

namespace Multitron\Impl;

use Multitron\Comms\TaskCommunicator;
use Throwable;

abstract class SimpleTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        try {
            $comm->sendProgress(0, 1);
            $success = $this->run($comm);
            $comm->sendProgress((int)$success, 1, (int)!$success);
            if (!$success) {
                $comm->error('Task failed');
            }
        } catch (Throwable $e) {
            $comm->error($e->getMessage());
        }
    }

    abstract protected function run(TaskCommunicator $comm): bool;
}
