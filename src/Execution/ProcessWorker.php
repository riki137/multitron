<?php

declare(strict_types=1);

namespace Multitron\Execution;

use Multitron\Execution\Handler\HandlerRegistry;
use Multitron\Message\StartTaskMessage;
use Multitron\Tree\Task;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\Message;
use Psr\Container\ContainerInterface;

class ProcessWorker
{
    public function __construct(
        private readonly IpcPeer $ipcPeer,
        private readonly ContainerInterface $taskContainer
    ) {
    }

    public function run(): void
    {
        $session = $this->ipcPeer->createStdioSession();
        $session->onRequest($this->handleRequest(...));
    }

    public function handleRequest(StartTaskMessage $message): void
    {
        $task = $this->taskContainer->get($message->taskId);
        if (!$task instanceof Task) {
            throw new \RuntimeException(
                'Task failed to load, received ' . get_debug_type($message)
            );
        }
        $task->execute();
    }
}
