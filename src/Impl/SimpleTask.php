<?php

declare(strict_types=1);

namespace Multitron\Impl;

use Multitron\Comms\TaskCommunicator;

abstract class SimpleTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $comm->setTotal(1);
        $success = $this->run($comm);
        if ($success) {
            $comm->addDone();
            $comm->sendProgress(true);
        } else {
            $comm->error('Task failed');
        }
    }

    abstract protected function run(TaskCommunicator $comm): bool;
}
