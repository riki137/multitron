<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\TaskState;
use StreamIpc\IpcPeer;

class DummyExecutionFactory implements ExecutionFactory
{
    public function __construct(
        private IpcPeer $peer,
        private ?int $exitCode = 0,
        private array $killResult = ['exitCode' => 0, 'stdout' => '', 'stderr' => '']
    ) {
    }

    public function launch(string $commandName, string $taskId, array $options, int $remainingTasks, IpcHandlerRegistry $registry, ?callable $onException = null): TaskState
    {
        [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $session = $this->peer->createStreamSession($a, $a);
        $exec = new DummyExecution($session, $this->exitCode, $this->killResult);
        $state = new TaskState($taskId, $exec);
        $registry->attach($state);
        return $state;
    }

    public function shutdown(): void
    {
    }
}

