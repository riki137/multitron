<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\TaskTreeBuilderFactory;

final readonly class TaskCommandDeps
{
    public function __construct(
        public TaskTreeBuilderFactory $taskTreeBuilderFactory,
        public TaskOrchestrator $taskOrchestrator
    ) {
    }
}
