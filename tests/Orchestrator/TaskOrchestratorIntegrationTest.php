<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use Multitron\Orchestrator\Output\ProgressOutputFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskQueue;
use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Execution;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\Output\ProgressOutput;
use Multitron\Tree\TaskNode;
use PHPUnit\Framework\TestCase;
use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\Transport\MessageTransport;
use Multitron\Tests\Fixtures\FakeTransport;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

class OrchestratorTestPeer extends IpcPeer
{
    public function createFakeSession(MessageTransport $transport): IpcSession
    {
        return $this->createSession($transport);
    }

    public function tick(?float $timeout = null): void
    {
        // no-op
    }
}

class FakeExecution implements Execution
{
    private int $ticksRemaining;
    public function __construct(private IpcSession $session, int $ticks, private int $exitCode)
    {
        $this->ticksRemaining = $ticks;
    }
    public function getSession(): IpcSession { return $this->session; }
    public function getExitCode(): ?int
    {
        if ($this->ticksRemaining > 0) {
            $this->ticksRemaining--;
            return null;
        }
        return $this->exitCode;
    }
    public function kill(): void {}
}

class FakeExecutionFactory implements ExecutionFactory
{
    /** @var array<string, array{exit:int,ticks:int}> */
    public array $config = [];
    public array $launched = [];
    public function __construct(private OrchestratorTestPeer $peer)
    {
    }
    public function launch(string $commandName, string $taskId, array $options): Execution
    {
        $this->launched[] = $taskId;
        $conf = $this->config[$taskId] ?? ['exit' => 0, 'ticks' => 0];
        $session = $this->peer->createFakeSession(new FakeTransport());
        return new FakeExecution($session, $conf['ticks'], $conf['exit']);
    }
    public function shutdown(): void {}
}

class DummyHandlerFactory implements IpcHandlerRegistryFactory
{
    public function create(): IpcHandlerRegistry
    {
        return new IpcHandlerRegistry();
    }
}

class CollectingOutput implements ProgressOutput
{
    /** @var TaskState[] */
    public array $started = [];
    /** @var TaskState[] */
    public array $completed = [];
    public function onTaskStarted(TaskState $state): void { $this->started[] = $state; }
    public function onTaskUpdated(TaskState $state): void {}
    public function onTaskCompleted(TaskState $state): void { $this->completed[$state->getTaskId()] = $state; }
    public function log(TaskState $state, string $message): void {}
    public function render(): void {}
}

final class TaskOrchestratorIntegrationTest extends TestCase
{
    public function testTasksRunWithDependenciesAndFailures(): void
    {
        $peer = new OrchestratorTestPeer();
        $execFactory = new FakeExecutionFactory($peer);
        $execFactory->config = [
            'a' => ['exit' => 0, 'ticks' => 0],
            'b' => ['exit' => 1, 'ticks' => 0],
            'c' => ['exit' => 0, 'ticks' => 0],
        ];
        $handlerFactory = new DummyHandlerFactory();
        $orch = new TaskOrchestrator(
            $peer,
            $execFactory,
            new class implements ProgressOutputFactory {
                public function create(TaskList $taskList, OutputInterface $output, IpcHandlerRegistry $registry): ProgressOutput { return new CollectingOutput(); }
            },
            $handlerFactory
        );

        $nodes = [
            'a' => TaskNode::leaf('a', fn() => null),
            'b' => TaskNode::leaf('b', fn() => null, ['a']),
            'c' => TaskNode::leaf('c', fn() => null, ['b']),
        ];
        $queue = new TaskQueue($nodes, new ArrayInput([]), 2);
        $output = new CollectingOutput();
        $exit = $orch->doRun('cmd', [TaskOrchestrator::OPTION_UPDATE_INTERVAL => 0.01], $queue, $output, new IpcHandlerRegistry());

        $this->assertSame(1, $exit);
        $this->assertSame(['a', 'b'], $execFactory->launched);
        $this->assertArrayHasKey('a', $output->completed);
        $this->assertArrayHasKey('b', $output->completed);
        $this->assertArrayHasKey('c', $output->completed);
        $this->assertSame(TaskStatus::SUCCESS, $output->completed['a']->getStatus());
        $this->assertSame(TaskStatus::ERROR, $output->completed['b']->getStatus());
        $this->assertSame(TaskStatus::SKIP, $output->completed['c']->getStatus());
    }
}

