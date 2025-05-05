<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Multitron\Comms\TaskCommunicator;

interface Task
{
    public function execute(TaskCommunicator $comm): int;
}
