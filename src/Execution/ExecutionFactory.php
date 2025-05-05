<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Multitron\Tree\Task;
use Symfony\Component\Process\Process;

interface ExecutionFactory
{
    public function launch(Task $task): Execution;
}
