<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\SimpleTaskGroupNode;
use Multitron\Tree\TaskNode;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeBuilderFactory;
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
        return new SimpleTaskGroupNode($this->getName(), $builder->consume());
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->orchestrator->run($this->getName(), $this->getRootNode(), $input, $output);
    }
}
