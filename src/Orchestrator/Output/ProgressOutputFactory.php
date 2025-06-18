<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\TaskList;
use Symfony\Component\Console\Output\OutputInterface;

interface ProgressOutputFactory
{
    /**
     * @param array<string, mixed> $options
     */
    public function create(TaskList $taskList, OutputInterface $output, IpcHandlerRegistry $registry, array $options): ProgressOutput;
}
