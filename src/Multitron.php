<?php
declare(strict_types=1);

namespace Multitron;

use Multitron\Console\InputConfiguration;
use Multitron\Container\Node\NonBlockingNode;
use Multitron\Container\Node\TaskFilteringNode;
use Multitron\Container\Node\TaskGroupNode;
use Multitron\Container\Node\TaskTreeProcessor;
use Multitron\Error\ErrorHandler;
use Multitron\Output\TableOutput;
use Multitron\Process\TaskRunner;
use Multitron\Process\TaskThread;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Multitron extends Command
{
    private int $concurrentTasks;

    private TaskTreeProcessor $tree;

    private TableOutput $tableOutput;

    public function __construct(
        private readonly TaskGroupNode $rootNode,
        private readonly string $bootstrapPath,
        ?int $concurrentTasks,
        private readonly ErrorHandler $errorHandler
    ) {
        $this->concurrentTasks = $concurrentTasks ?? (int)shell_exec('nproc');
        $this->tableOutput = new TableOutput();
        parent::__construct('multitron');
    }

    protected function configure(): void
    {
        $this->setDescription('Runs a multitron task tree');
        $conf = new InputConfiguration();
        $this->tree = new TaskTreeProcessor($this->rootNode);
        foreach ($this->tree->getNodes() as $node) {
            $node->configure($conf);
        }
        $this->tableOutput->configure($conf);
        $this->setDefinition($conf->toDefinition());
        $this->addArgument(
            'task',
            InputArgument::OPTIONAL,
            'The tasks to run. Separated by comma. Uses fnmatch() for patterns. You can use % instead of *',
            '*'
        );
        $this->addOption('direct', 'd', InputOption::VALUE_NEGATABLE, 'Run all tasks in the main process', false);
        $this->addOption(TaskThread::MEMORY_LIMIT, null, InputOption::VALUE_REQUIRED, 'Set memory limit for each process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption(TaskThread::MEMORY_LIMIT) !== null) {
            ini_set('memory_limit', $input->getOption(TaskThread::MEMORY_LIMIT));
        }

        $node = $this->rootNode;
        if ($input->getArgument('task') !== '*') {
            $node = new TaskFilteringNode('_rootF', $node, $input->getArgument('task'));
        }
        if ($input->getOption('direct')) {
            $node = new NonBlockingNode('_rootD', fn() => yield $node);
        }
        if ($node !== $this->rootNode) {
            $this->tree = new TaskTreeProcessor($node);
        }
        $runner = new TaskRunner($this->tree, $this->concurrentTasks, $this->bootstrapPath, $this->errorHandler, $input->getOptions());
        assert($output instanceof ConsoleOutputInterface);
        $tableFuture = $this->tableOutput->run($runner, $input, $output);
        $exitCode = $runner->runAll();
        $tableFuture->await();
        return $exitCode->await();
    }
}
