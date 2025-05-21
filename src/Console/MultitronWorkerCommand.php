<?php

declare(strict_types=1);

namespace Multitron\Console;

use JetBrains\PhpStorm\NoReturn;
use Multitron\Comms\TaskCommunicator;
use Multitron\Message\ContainerLoadedMessage;
use Multitron\Message\StartTaskMessage;
use Multitron\Orchestrator\TaskList;
use Multitron\Execution\Task;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME)]
final class MultitronWorkerCommand extends Command
{
    public const NAME = 'multitron:worker';

    public function __construct(private readonly ContainerInterface $container, private readonly IpcPeer $peer)
    {
        parent::__construct(self::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $session = $this->peer->createStdioSession();
        $session->onRequest(static fn(Message $message) => $message instanceof ContainerLoadedMessage ? $message : null);
        $startTask = null;
        $session->onRequest(function (Message $message) use ($session, $input, &$startTask) {
            if (!$message instanceof StartTaskMessage) {
                return null;
            }
            $startTask = $message;
            return new LogMessage('Task started: ' . $message->taskId);
        });

        while ($startTask === null) {
            $this->peer->tick();
        }

        $command = $this->getApplication()->find($startTask->commandName);
        if (!$command instanceof AbstractMultitronCommand) {
            throw new RuntimeException('Command not found');
        }
        $list = new TaskList($this->container, $command->getRootNode(), $input);
        foreach ($list->getNodes() as $node) {
            if ($node->getId() === $startTask->taskId) {
                $comm = new TaskCommunicator($session, $startTask->options);
                ($node->getFactory($input))()->execute($comm);
                $comm->shutdown();
                return 0;
            }
        }

        throw new RuntimeException('Task "' . $startTask->taskId . '" not found in command "' . $startTask->commandName . '"');
    }
}
