<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\MultitronConfig;
use Multitron\Orchestrator\TaskOrchestrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MultitronCommand extends Command
{
    public function __construct(
        private readonly TaskOrchestrator $orchestrator,
        private readonly MultitronConfig $config
    ) {
        parent::__construct();
    }

    public static function getDefaultName(): ?string
    {
        return 'multitron';
    }

    protected function configure(): void
    {
        $this->addOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'Max concurrent tasks', $this->config->concurrency);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->config->concurrency = (int)$input->getOption('concurrency');

        return $this->orchestrator->run();
    }
}
