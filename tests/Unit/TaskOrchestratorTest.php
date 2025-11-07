<?php

declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Execution\ExecutionFactory;
use Multitron\Tests\Mocks\DummyExecution;
use Multitron\Tests\Mocks\DummyOutput;
use Multitron\Tests\Mocks\OrchestratorPeer;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Tree\TaskNode;
use Multitron\Tree\TaskTreeQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StreamIpc\InvalidStreamException;

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

