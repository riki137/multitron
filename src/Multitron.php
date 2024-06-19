<?php
declare(strict_types=1);

namespace Multitron;

use Multitron\Container\Node\NoWorkersNode;
use Multitron\Container\Node\TaskFilteringNode;
use Multitron\Container\Node\TaskGroupNode;
use Multitron\Container\Node\TaskTreeProcessor;
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
        ?int $concurrentTasks = null,
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
        EventLoop::setErrorHandler(fn($t) => Debugger::log($t, 'EventLoop'));
        Debugger::$strictMode = false;
        $node = $this->rootNode;
        if ($input->getArgument('task') !== '*') {
            $node = new TaskFilteringNode('root-filtered', $node, $input->getArgument('task'));
        }
        if ($input->getOption('direct')) {
            $node = new NoWorkersNode('root-no-workers', fn() => yield $node);
        }
        $tree = new TaskTreeProcessor($node);
        $runner = new TaskRunner($tree, $this->concurrentTasks, $this->bootstrapPath);

        assert($output instanceof ConsoleOutputInterface);
        new TableOutput($runner, $output);

        $runner->runAll();
        EventLoop::run();
        $runner->shutdown();

        return 0;
    }
}
