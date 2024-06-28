<?php
declare(strict_types=1);

namespace Multitron;

use Multitron\Container\Node\NonBlockingNode;
use Multitron\Container\Node\TaskFilteringNode;
use Multitron\Container\Node\TaskGroupNode;
use Multitron\Container\Node\TaskTreeProcessor;
use Multitron\Error\ErrorHandler;
use Multitron\Output\TableOutput;
use Multitron\Process\TaskRunner;
use Revolt\EventLoop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class Multitron extends Command
{
    private int $concurrentTasks;

    public function __construct(
        private readonly TaskGroupNode $rootNode,
        private readonly string $bootstrapPath,
        ?int $concurrentTasks,
        private readonly ErrorHandler $errorHandler
    ) {
        parent::__construct('multitron');
        $this->concurrentTasks = $concurrentTasks ?? (int)shell_exec('nproc');
    }

    protected function configure()
    {
        $this->setDescription('Runs a multitron task tree');
        $this->addArgument(
            'task',
            InputArgument::OPTIONAL,
            'The task to run. Separated by comma. Uses fnmatch() for patterns.',
            '*'
        );
        $this->addOption(
            'direct',
            'd',
            InputOption::VALUE_NEGATABLE,
            'Run tasks directly without workers',
            false
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Debugger::$strictMode = false;
        $node = $this->rootNode;
        if ($input->getArgument('task') !== '*') {
            $node = new TaskFilteringNode('_rootF', $node, $input->getArgument('task'));
        }
        if ($input->getOption('direct')) {
            $node = new NonBlockingNode('_rootD', fn() => yield $node);
        }
        $tree = new TaskTreeProcessor($node);
        $runner = new TaskRunner($tree, $this->concurrentTasks, $this->bootstrapPath, $this->errorHandler);

        assert($output instanceof ConsoleOutputInterface);
        new TableOutput($runner, $output);

        $runner->runAll();
        EventLoop::run();

        return 0;
    }
}
