<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Multitron\Comms\TaskCommunicator;

interface Task
{
    public function execute(TaskCommunicator $comm): void;
}
