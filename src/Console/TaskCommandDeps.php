<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\TaskTreeBuilderFactory;

final readonly class TaskCommandDeps
{
    /**
     * @internal
     * @param int<0,max>|null $defaultConcurrency Default concurrency level for task execution. Null means amount of CPU threads.
     */
    public function __construct(
        public TaskTreeBuilderFactory $taskTreeBuilderFactory,
        public TaskOrchestrator $taskOrchestrator,
        public ?int $defaultConcurrency = null,
    ) {
    }
}
