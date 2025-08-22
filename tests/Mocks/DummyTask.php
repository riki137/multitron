<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;

class DummyTask implements Task
{
    public function execute(TaskCommunicator $c): void
    {
    }
}

