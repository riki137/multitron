<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\TaskList;
use Symfony\Component\Console\Output\OutputInterface;

interface ProgressOutputFactory
{
    public function create(TaskList $taskList, OutputInterface $output, IpcHandlerRegistry $registry): ProgressOutput;
}
