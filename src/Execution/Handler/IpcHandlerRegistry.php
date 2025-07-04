<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler;

use Closure;
use Multitron\Orchestrator\TaskState;
use StreamIpc\IpcSession;
use StreamIpc\Message\Message;

final class IpcHandlerRegistry
{
    /** @var array<Closure(Message, TaskState): (Message|null)> */
    private array $requestHandlers = [];

    /** @var array<Closure(Message, TaskState): void> */
    private array $messageHandlers = [];

    /**
     * @param Closure(Message, TaskState): (Message|null) $handler
     */
    public function onRequest(Closure $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    /**
     * @param Closure(Message, TaskState): void $handler
     */
    public function onMessage(Closure $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    /**
     * Attach all registered callbacks to the IPC session associated with the
     * provided task state. No-op if the task has not started yet.
     */
    public function attach(TaskState $state): void
    {
        $execution = $state->getExecution();
        if ($execution === null) {
            return;
        }
        $session = $execution->getSession();
        foreach ($this->requestHandlers as $handler) {
            $session->onRequest(
                fn(Message $message, IpcSession $session) => $handler($message, $state)
            );
        }
        foreach ($this->messageHandlers as $handler) {
            $session->onMessage(
                fn(Message $message, IpcSession $session) => $handler($message, $state)
            );
        }
    }
}
