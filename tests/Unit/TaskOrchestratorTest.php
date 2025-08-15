<?php

declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Execution\Execution;
use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Orchestrator\Output\ProgressOutput;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Tree\TaskNode;
use Multitron\Tree\TaskTreeQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StreamIpc\InvalidStreamException;
use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\Message\Message;
use StreamIpc\Transport\MessageTransport;

class OrchestratorTransport implements MessageTransport
{
    public function send(Message $message): void
    {
    }

    public function getReadStreams(): array
    {
        return [];
    }

    public function readFromStream($stream): array
    {
        return [];
    }
}

class OrchestratorPeer extends IpcPeer
{
    public IpcSession $session;

    public function __construct()
    {
        parent::__construct();
        $this->session = $this->createSession(new OrchestratorTransport());
    }

    public function makeSession(): IpcSession
    {
        return $this->createSession(new OrchestratorTransport());
    }

    public function tick(?float $timeout = null): void
    {
    }
}

class DummyExecution implements Execution
{
    public function __construct(private IpcSession $session) {}

    public function getSession(): IpcSession
    {
        return $this->session;
    }

    public function getExitCode(): ?int
    {
        return 1;
    }

    public function kill(): array
    {
        return ['exitCode' => 1, 'stdout' => '', 'stderr' => ''];
    }
}

class DummyOutput implements ProgressOutput
{
    public array $completed = [];

    public function onTaskStarted(TaskState $state): void {}

    public function onTaskUpdated(TaskState $state): void {}

    public function onTaskCompleted(TaskState $state): void
    {
        $this->completed[] = $state;
    }

    public function log(TaskState $state, string $message): void {}

    public function render(): void {}
}

final class TaskOrchestratorTest extends TestCase
{
    private TaskOrchestrator $orch;
    private OrchestratorPeer $peer;
    private TaskTreeQueue $queue;
    private DummyOutput $output;

    protected function setUp(): void
    {
        $this->peer = new OrchestratorPeer();
        $execFactory = $this->createStub(ExecutionFactory::class);
        $progressFactory = $this->createStub(\Multitron\Orchestrator\Output\ProgressOutputFactory::class);
        $handlerFactory = $this->createStub(IpcHandlerRegistryFactory::class);
        $this->orch = new TaskOrchestrator($this->peer, $execFactory, $progressFactory, $handlerFactory);
        $taskList = new TaskList(new TaskNode('root'));
        $this->queue = new TaskTreeQueue($taskList);
        $this->output = new DummyOutput();
    }

    public function testRethrowsNonInvalidStreamException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->orch->handleStreamException(new RuntimeException('boom'), [], $this->queue, $this->output);
    }

    public function testDelegatesToOnErrorWhenSessionMatches(): void
    {
        $exec = new DummyExecution($this->peer->session);
        $state = new TaskState('t1', $exec);
        $this->orch->handleStreamException(new InvalidStreamException($this->peer->session), ['t1' => $state], $this->queue, $this->output);
        $this->assertSame(TaskStatus::ERROR, $state->getStatus());
        $this->assertCount(1, $this->output->completed);
    }

    public function testIgnoresWhenNoStateMatches(): void
    {
        $exec = new DummyExecution($this->peer->session);
        $state = new TaskState('t1', $exec);
        $other = $this->peer->makeSession();
        $this->orch->handleStreamException(new InvalidStreamException($other), ['t1' => $state], $this->queue, $this->output);
        $this->assertCount(0, $this->output->completed);
    }
}

