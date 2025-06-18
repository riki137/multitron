<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\TaskList;
use Symfony\Component\Console\Output\OutputInterface;

final class ChainProgressOutputFactory implements ProgressOutputFactory
{
    /**
     * @var ProgressOutputFactory[]
     */
    private array $factories;

    public function __construct(ProgressOutputFactory ...$factories)
    {
        $this->factories = $factories;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function create(TaskList $taskList, OutputInterface $output, IpcHandlerRegistry $registry, array $options): ProgressOutput
    {
        $outputs = [];
        foreach ($this->factories as $factory) {
            $outputs[] = $factory->create($taskList, $output, $registry, $options);
        }
        return new ChainProgressOutput(...$outputs);
    }
}
