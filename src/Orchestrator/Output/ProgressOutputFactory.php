<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Execution\Handler\IpcHandlerRegistry;
use Symfony\Component\Console\Output\OutputInterface;

interface ProgressOutputFactory
{
    public function create(OutputInterface $output, IpcHandlerRegistry $registry): ProgressOutput;
}
