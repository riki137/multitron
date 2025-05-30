<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Comms\TaskCommunicator;
use Multitron\Message\ContainerLoadedMessage;
use Multitron\Message\StartTaskMessage;
use Multitron\Tree\TaskTreeBuilderFactory;
use StreamIpc\IpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME)]
final class MultitronWorkerCommand extends Command
{
    public const NAME = 'multitron:worker';

    public function __construct(private readonly TaskTreeBuilderFactory $builderFactory, private readonly IpcPeer $peer)
    {
        parent::__construct(self::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $session = $this->peer->createStdioSession();
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
            $this->peer->tick();
        }

        $application = $this->getApplication();
        if ($application === null) {
            throw new RuntimeException('Console Application not initialized. You need to run the command in a console context.');
        }
        $command = $application->find($startTask->commandName);
        if (!$command instanceof AbstractMultitronCommand) {
            throw new RuntimeException('Command not found');
        }
        $list = /* TODO */[];
        foreach ($list as $node) {
            if ($node->getId() === $startTask->taskId && $node->isLeaf()) {
                $comm = new TaskCommunicator($session, $startTask->options);
                try {
                    ($node->getFactory($input))()->execute($comm);
                } finally {
                    $comm->shutdown();
                }
                return 0;
            }
        }

        throw new RuntimeException('Task "' . $startTask->taskId . '" not found in command "' . $startTask->commandName . '"');
    }
}
