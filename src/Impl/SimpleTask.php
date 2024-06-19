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
            $comm->setTotal(1);
            $success = $this->run($comm);
            if ($success) {
                $comm->addDone();
                $comm->sendProgress(true);
            } else {
                $comm->error('Task failed');
            }
        } catch (Throwable $e) {
            $comm->error($e->getMessage());
        }
    }

    abstract protected function run(TaskCommunicator $comm): bool;
}
