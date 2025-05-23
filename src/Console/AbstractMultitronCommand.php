<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\SimpleTaskGroupNode;
use Multitron\Tree\TaskNode;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeBuilderFactory;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractMultitronCommand extends Command
{
    public function __construct(private readonly TaskTreeBuilderFactory $builderFactory, private readonly TaskOrchestrator $orchestrator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(TaskOrchestrator::OPTION_CONCURRENCY, 'c', InputOption::VALUE_REQUIRED, 'Max concurrent tasks executed');
    }

    abstract public function getNodes(TaskTreeBuilder $builder): void;

    final public function getRootNode(): TaskNode
    {
        $builder = $this->builderFactory->create();
        $this->getNodes($builder);
        $name = $this->getName();
        if (!is_string($name)) {
            throw new RuntimeException('Command ' . static::class . ' has no name configured. Add #[AsCommand(name: "...")] to the class.');
        }
        return new SimpleTaskGroupNode((string)$this->getName(), $builder->consume());
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->orchestrator->run((string)$this->getName(), $this->getRootNode(), $input, $output);
    }
}
