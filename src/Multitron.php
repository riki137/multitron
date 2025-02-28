<?php
declare(strict_types=1);

namespace Multitron;

use InvalidArgumentException;
use Multitron\Console\InputConfiguration;
use Multitron\Console\MultitronConfig;
use Multitron\Container\Node\FilteringTaskNode;
use Multitron\Container\Node\NonBlockingNode;
use Multitron\Container\Node\TaskNode;
use Multitron\Output\TableOutput;
use Multitron\Process\TaskRunner;
use Multitron\Process\TaskThread;
use Revolt\EventLoop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Tracy\Debugger;
use function Amp\Future\await;

/**
 * Multitron command for running task trees concurrently.
 */
class Multitron extends Command
{
    private const DEFAULT_NAME = 'multitron';

    private TableOutput $tableOutput;

    /**
     * @param TaskNode $rootNode The root node of the task tree
     * @param MultitronConfig $config Configuration settings for Multitron
     * @param string|null $name Command name (defaults to 'multitron')
     *
     * @throws InvalidArgumentException If rootNode or config is invalid
     */
    public function __construct(
        private readonly TaskNode $rootNode,
        private readonly MultitronConfig $config,
        ?string $name = null,
    ) {

        EventLoop::setErrorHandler(function (Throwable $t) {
            Debugger::log($t, 'eventroot');
        });
        $this->tableOutput = new TableOutput();
        parent::__construct($name ?? self::DEFAULT_NAME);
    }

    /**
     * Configures the command with all necessary arguments and options.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setDescription(sprintf('Runs a multitron task tree (%s)', $this->rootNode->getId()));
        $input = new InputConfiguration();

        foreach ($this->rootNode->getProcessedNodes() as $node) {
            $node->configure($input);
        }

        $this->tableOutput->configure($input);
        $this->setDefinition($input->toDefinition());

        $this->addArgument(
            'filter',
            InputArgument::OPTIONAL,
            'The tasks to run. Separated by comma. Uses fnmatch() for patterns. You can use % instead of *',
            '*'
        );

        $this->addOption(
            'direct',
            'd',
            InputOption::VALUE_NEGATABLE,
            'Run all tasks in the main process',
            false
        );

        $this->addOption(
            TaskThread::MEMORY_LIMIT,
            null,
            InputOption::VALUE_REQUIRED,
            'Set memory limit for each process'
        );

        $this->addOption(
            'concurrency',
            null,
            InputOption::VALUE_REQUIRED,
            'Set limit for concurrent processes. Defaults to amount of CPU cores (nproc)',
            $this->config->getConcurrentTasks()
        );
    }

    /**
     * Executes the command with the given input and output interfaces.
     *
     * @param InputInterface $input Command input interface
     * @param OutputInterface $output Command output interface
     *
     * @return int Exit code (0 for success, non-zero for failure)
     * @throws InvalidArgumentException If input validation fails
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->validateInput($input);

        if (($memoryLimit = $input->getOption(TaskThread::MEMORY_LIMIT)) !== null) {
            ini_set('memory_limit', $memoryLimit);
        }

        if (!$output instanceof ConsoleOutputInterface) {
            throw new InvalidArgumentException('Output must be an instance of ConsoleOutputInterface');
        }

        $node = $this->rootNode;
        $filterPattern = $input->getArgument('filter');

        if ($filterPattern !== '*') {
            $node = new FilteringTaskNode($node, $filterPattern);
        }

        if ($input->getOption('direct')) {
            $node = new NonBlockingNode($node);
        }

        $runner = new TaskRunner($node, $this->config, $input->getOptions());
        $tableFuture = $this->tableOutput->run($runner, $input, $output);
        $exitCode = $runner->runAll();
        return await([$tableFuture, $exitCode])[1];
    }

    private function validateInput(InputInterface $input): void
    {
        $filterPattern = $input->getArgument('filter');
        if (!is_string($filterPattern)) {
            throw new InvalidArgumentException('Filter pattern must be a string');
        }

        if (($memoryLimit = $input->getOption(TaskThread::MEMORY_LIMIT)) !== null) {
            if (!preg_match('/^(-1|\d+[KMG]?)$/i', $memoryLimit)) {
                throw new InvalidArgumentException(
                    'Invalid memory limit format. Use format like "128M", "1G", or "-1" for unlimited'
                );
            }
        }
    }
}
