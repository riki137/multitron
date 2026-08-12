<?php

declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Execution\ExecutionFactory;
use Multitron\Tests\Mocks\DummyExecution;
use Multitron\Tests\Mocks\DummyOutput;
use Multitron\Tests\Mocks\OrchestratorPeer;
use Multitron\Execution\Handler\DefaultIpcHandlerRegistryFactory;
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

    public function testOnErrorWithNullExecution(): void
    {
        $state = new TaskState('t1', null);
        $this->orch->onError($state, $this->queue, $this->output);
        
        $this->assertSame(TaskStatus::ERROR, $state->getStatus());
        $this->assertCount(1, $this->output->completed);
        $this->assertCount(1, $this->output->logs);
        $this->assertStringContainsString('No execution found', $this->output->logs[0][1]);
    }

    public function testOnErrorWithExecution(): void
    {
        $exec = new DummyExecution($this->peer->session);
        $state = new TaskState('t1', $exec);
        
        // Add a task to the queue so we can test skipping
        $taskList = new TaskList(new TaskNode('root', null, [
            new TaskNode('t1', fn() => new \Multitron\Tests\Mocks\DummyTask()),
            new TaskNode('t2', fn() => new \Multitron\Tests\Mocks\DummyTask(), dependencies: ['t1']),
        ]));
        $queue = new TaskTreeQueue($taskList);
        
        $this->orch->onError($state, $queue, $this->output);
        
        $this->assertSame(TaskStatus::ERROR, $state->getStatus());
        $this->assertGreaterThanOrEqual(1, $this->output->completed);
        $this->assertCount(1, $this->output->logs);
        $this->assertStringContainsString('Worker exited with code', $this->output->logs[0][1]);
    }

    public function testDoRunWithInvalidUpdateInterval(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Update interval must be a number');
        
        $registry = new \Multitron\Execution\Handler\IpcHandlerRegistry();
        
        $this->orch->doRun(
            'test',
            ['update-interval' => 'invalid'],
            $this->queue,
            $this->output,
            $registry
        );
    }

    public function testStreamExceptionCallbackRemovesFailedTaskFromCallerStates(): void
    {
        $session1 = $this->peer->session;
        $session2 = $this->peer->makeSession();

        $exec1 = new class($session1) implements \Multitron\Execution\Execution {
            public int $exitCalls = 0;

            public function __construct(private \StreamIpc\IpcSession $session)
            {
            }

            public function getSession(): \StreamIpc\IpcSession
            {
                return $this->session;
            }

            public function getExitCode(): ?int
            {
                $this->exitCalls++;
                return null;
            }

            public function kill(): array
            {
                return ['exitCode' => 1, 'stdout' => '', 'stderr' => ''];
            }
        };
        $exec2 = new DummyExecution($session2, 0);

        $capturedCallback = null;
        $execFactory = $this->createMock(ExecutionFactory::class);
        $execFactory->method('launch')->willReturnCallback(
            function (string $commandName, string $taskId, array $options, int $remaining, $registry, ?callable $onException = null) use (&$capturedCallback, $exec1, $exec2, $session1) {
                if ($taskId === 't1') {
                    $capturedCallback = $onException;
                    return new TaskState('t1', $exec1);
                }
                // simulate task t1's IPC session breaking while t2 is being launched
                if ($capturedCallback !== null) {
                    ($capturedCallback)(new InvalidStreamException($session1));
                }
                return new TaskState('t2', $exec2);
            }
        );

        $progressFactory = $this->createStub(\Multitron\Orchestrator\Output\ProgressOutputFactory::class);
        $handlerFactory = $this->createStub(IpcHandlerRegistryFactory::class);
        $orch = new TaskOrchestrator($this->peer, $execFactory, $progressFactory, $handlerFactory);

        $taskList = new TaskList(new TaskNode('root', null, [
            new TaskNode('t1', fn() => new \Multitron\Tests\Mocks\DummyTask()),
            new TaskNode('t2', fn() => new \Multitron\Tests\Mocks\DummyTask()),
        ]));
        $queue = new TaskTreeQueue($taskList, 2);
        $registry = new \Multitron\Execution\Handler\IpcHandlerRegistry();

        $orch->doRun('test', ['update-interval' => 0.01], $queue, $this->output, $registry);

        // t1's execution must never be polled again after the callback already failed it,
        // otherwise a later non-null exit code would double-fire onTaskCompleted and flip its status back
        $this->assertSame(1, $exec1->exitCalls);
        $this->assertCount(2, $this->output->completed);
        $this->assertSame(TaskStatus::ERROR, $this->output->completed[0]->getStatus());
        $this->assertSame(TaskStatus::SUCCESS, $this->output->completed[1]->getStatus());
    }

    public function testOnErrorWithSkippedDependencies(): void
    {
        $exec = new DummyExecution($this->peer->session);
        $state = new TaskState('t1', $exec);
        
        // Create a more complex dependency tree
        $taskList = new TaskList(new TaskNode('root', null, [
            new TaskNode('t1', fn() => new \Multitron\Tests\Mocks\DummyTask()),
            new TaskNode('t2', fn() => new \Multitron\Tests\Mocks\DummyTask(), dependencies: ['t1']),
            new TaskNode('t3', fn() => new \Multitron\Tests\Mocks\DummyTask(), dependencies: ['t2']),
        ]));
        $queue = new TaskTreeQueue($taskList);
        
        $this->orch->onError($state, $queue, $this->output);
        
        // Should have skipped t2 and t3
        $this->assertGreaterThanOrEqual(2, $this->output->completed);
    }
}

