<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use Multitron\Comms\TaskCommunicator;
use Multitron\Tree\Partition\PartitionedTask;

class DummyPartitionTask extends PartitionedTask
{
    public function execute(TaskCommunicator $c): void
    {
    }
}

