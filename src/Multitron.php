<?php
declare(strict_types=1);

namespace Multitron;

use Multitron\Container\Node\TaskGroupNode;
use Multitron\Container\Node\TaskTreeProcessor;
use Multitron\Container\TaskContainer;
use Multitron\Output\TableOutput;
use Multitron\Process\TaskRunner;
use Revolt\EventLoop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Multitron extends Command
{
    private int $concurrentTasks;
    private TaskTreeProcessor $tree;

    public function __construct(
        TaskGroupNode $rootNode,
        private readonly string $bootstrapPath,
        ?int $concurrentTasks = null,
    ) {
        parent::__construct('multitron');
        $this->concurrentTasks = $concurrentTasks ?? (int)shell_exec('nproc');
        $this->tree = new TaskTreeProcessor($rootNode);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runner = new TaskRunner($this->tree, $this->concurrentTasks, $this->bootstrapPath);

        assert($output instanceof ConsoleOutputInterface);
        new TableOutput($runner, $output);

        $runner->runAll();
        EventLoop::run();
        $runner->shutdown();

        return 0;
    }
}
