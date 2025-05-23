<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler;

use Closure;
use Multitron\Orchestrator\TaskState;
use PhpStreamIpc\Message\Message;

final class IpcHandlerRegistry
{
    /** @var array<Closure(Message, TaskState): mixed> */
    private array $requestHandlers = [];

    /** @var array<Closure(Message, TaskState): mixed> */
    private array $messageHandlers = [];

    public function onRequest(Closure $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    public function onMessage(Closure $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    public function attach(TaskState $state): void
    {
        $execution = $state->getExecution();
        if ($execution === null) {
            return;
        }
        $session = $execution->getSession();
        foreach ($this->requestHandlers as $handler) {
            $session->onRequest(fn(Message $message) => $handler($message, $state));
        }
        foreach ($this->messageHandlers as $handler) {
            $session->onMessage(fn(Message $message) => $handler($message, $state));
        }
    }
}
