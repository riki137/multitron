<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Comms\IpcAdapter;
use Multitron\Comms\TaskCommunicator;
use Multitron\Message\ContainerLoadedMessage;
use Multitron\Message\StartTaskMessage;
use Multitron\Orchestrator\TaskOrchestrator;
use RuntimeException;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME)]
final class WorkerCommand extends Command
{
    public const NAME = 'multitron:worker';

    /**
     * @internal This command is executed in worker processes only.
     */
    public function __construct(private readonly IpcAdapter $ipc)
    {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Starts a worker process to execute tasks.');
        $this->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'Connection string for the IPC server. Not required for default STDIO.');
    }

    /** {@inheritDoc} */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $input->getOption('connection');
        if (!is_string($connection)) {
            $connection = null;
        }
        $session = $this->ipc->createWorkerSession($connection);
        $session->onRequest(static fn(Message $message) => $message instanceof ContainerLoadedMessage ? $message : null);
        $startTask = null;
        $session->onRequest(function (Message $message) use (&$startTask) {
            if (!$message instanceof StartTaskMessage) {
                return null;
            }
            $startTask = $message;
            return new LogMessage('Task started: ' . $message->taskId);
        });

        while ($startTask === null) {
            $this->ipc->getPeer()->tick();
        }

        $memoryLimit = $startTask->options[TaskOrchestrator::OPTION_MEMORY_LIMIT];
        ini_set('memory_limit', is_string($memoryLimit) ? $memoryLimit : TaskOrchestrator::DEFAULT_MEMORY_LIMIT);

        $application = $this->getApplication();
        if ($application === null) {
            throw new RuntimeException('Console Application not initialized. You need to run the command in a console context.');
        }
        $command = $application->find($startTask->commandName);
        if (!$command instanceof TaskCommand) {
            throw new RuntimeException('Command not found');
        }
        foreach ($command->getTaskList() as $node) {
            if ($node->id === $startTask->taskId) {
                $comm = new TaskCommunicator($session, $startTask->options);
                try {
                    ($node->factory)($input)->execute($comm);
                } finally {
                    $comm->shutdown();
                }
                return Command::SUCCESS;
            }
        }

        throw new RuntimeException('Task "' . $startTask->taskId . '" not found in command "' . $startTask->commandName . '"');
    }
}
