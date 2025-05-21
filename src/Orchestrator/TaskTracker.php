<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Message\TaskProgress;
use Multitron\Orchestrator\Output\ProgressOutput;
use Multitron\Tree\TaskNode;

class TaskTracker
{
    /** @var array<string, TaskState> */
    private array $states = [];

    public function __construct(
    ) {
    }
}
