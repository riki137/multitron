<?php

declare(strict_types=1);

namespace Multitron\Impl;

use Multitron\Comms\TaskCommunicator;
use Throwable;

interface Task
{
    /**
     * @throws Throwable if no error is thrown during the task execution, task is considered successful
     */
    public function execute(TaskCommunicator $comm): void;
}
