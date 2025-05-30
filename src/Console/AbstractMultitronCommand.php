<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\TaskNode;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeBuilderFactory;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
        $this->addArgument('pattern', InputArgument::OPTIONAL,
            'fnmatch() pattern to filter tasks. You can optionally use % instead of * for wildcards. Works for groups too.');
        $this->addOption(TaskOrchestrator::OPTION_CONCURRENCY, 'c', InputOption::VALUE_REQUIRED, 'Max concurrent tasks executed');
        $this->addOption(TaskOrchestrator::OPTION_UPDATE_INTERVAL, 'u', InputOption::VALUE_REQUIRED, 'Update interval in seconds', TaskOrchestrator::DEFAULT_UPDATE_INTERVAL);
    }

    abstract public function getNodes(TaskTreeBuilder $builder): void;

    final public function getRootNode(?InputInterface $input = null): TaskNode
    {
        $builder = $this->builderFactory->create();
        $this->getNodes($builder);
        $name = $this->getName();
        if (!is_string($name)) {
            throw new RuntimeException('Command ' . static::class . ' has no name configured. Add #[AsCommand(name: "...")] to the class.');
        }

        $pattern = $input?->getArgument('pattern');
        if (is_string($pattern) && trim($pattern) !== '') {
            $pattern = strtr($pattern, ['%' => '*']);
            // TODO
        }

        return TaskNode::group($this->getName(), $builder->build());
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->getApplication()->has(MultitronWorkerCommand::NAME)) {
            throw new RuntimeException(MultitronWorkerCommand::class . ' command not found. Please add it to your ' . Application::class . ' in your Dependency Injection container.');
        }
        return $this->orchestrator->run((string)$this->getName(), $this->getRootNode($input), $input, $output);
    }
}
