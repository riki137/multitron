<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Multitron\Console\TableOutput;
use Multitron\Execution\ExecutionFactory;
use Multitron\MultitronConfig;
use Multitron\Tree\TaskRootTree;
use Symfony\Component\Process\Process;

final readonly class TaskOrchestrator
{
    public function __construct(
        private TaskRootTree $rootTree,
        private ExecutionFactory $executionFactory,
        private MultitronConfig $config,
        private TableOutput $output,
    ) {
    }

    /**
     * Run all tasks in the root tree, up to the configured concurrency
     * (or number of CPUs if none set), and return 0 if all succeeded or 1 if any failed.
     */
    public function run(): int
    {
        $maxConcurrent = $this->config->concurrency ?? $this->detectCpuCount();
        $tracker = new TaskTracker($maxConcurrent, $this->rootTree, $this->executionFactory, $this->output);

        return $tracker->run();
    }

    /**
     * Attempt to discover the number of logical CPUs on this host.
     */
    private function detectCpuCount(): int
    {
        // Windows
        if (stripos(PHP_OS, 'WIN') === 0) {
            $proc = new Process(['wmic', 'cpu', 'get', 'NumberOfLogicalProcessors']);
            $proc->run();
            if ($proc->isSuccessful()) {
                $lines = array_values(array_filter(preg_split('/\r?\n/', trim($proc->getOutput()))));
                if (isset($lines[1])) {
                    return (int) $lines[1];
                }
            }
        } else {
            // Linux / macOS / *nix
            $proc = new Process(['nproc']);
            $proc->run();
            if ($proc->isSuccessful()) {
                return max(1, (int) trim($proc->getOutput()));
            }
        }

        // Fallback
        return 1;
    }
}
