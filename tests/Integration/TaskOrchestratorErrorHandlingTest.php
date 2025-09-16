<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Execution;
use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Execution\Task;
use Multitron\Orchestrator\Output\ProgressOutput;
use Multitron\Orchestrator\Output\ProgressOutputFactory;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeQueue;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\NativeIpcPeer;
use StreamIpc\InvalidStreamException;
use StreamIpc\Transport\TimeoutException;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskOrchestratorErrorHandlingTest extends TestCase
{
    public function testLogsTimeoutWhenPollingTask(): void
    {
        $peer = $this->getMockBuilder(IpcPeer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['tick'])
            ->getMockForAbstractClass();

        $tickCalls = 0;
        $peer->expects($this->exactly(2))
            ->method('tick')
            ->willReturnCallback(function (?float $timeout) use (&$tickCalls): void {
                self::assertSame(0.01, $timeout);
                $tickCalls++;
                if ($tickCalls === 1) {
                    throw new TimeoutException();
                }
            });

        $executionPeer = new NativeIpcPeer();
        $executionFactory = new class($executionPeer) implements ExecutionFactory {
            public function __construct(private NativeIpcPeer $peer)
            {
            }

            public function launch(string $commandName, string $taskId, array $options, int $remainingTasks, IpcHandlerRegistry $registry, ?callable $onException = null): TaskState
            {
                [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                $session = $this->peer->createStreamSession($a, $a);
                $execution = new class($session) implements Execution {
                    public function __construct(private IpcSession $session)
                    {
                    }

                    public function getSession(): IpcSession
                    {
                        return $this->session;
                    }

                    public function getExitCode(): ?int
                    {
                        return 0;
                    }

                    public function kill(): array
                    {
                        return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
                    }
                };
                $state = new TaskState($taskId, $execution);
                $registry->attach($state);

                return $state;
            }

            public function shutdown(): void
            {
            }
        };

        $orchestrator = new TaskOrchestrator(
            $peer,
            $executionFactory,
            $this->createProgressOutputFactory(),
            $this->createRegistryFactory(),
        );

        $output = new RecordingProgressOutput();
        $registry = new IpcHandlerRegistry();
        $queue = $this->createQueue();

        $result = $orchestrator->doRun(
            'demo',
            [TaskOrchestrator::OPTION_UPDATE_INTERVAL => 0.0],
            $queue,
            $output,
            $registry
        );

        self::assertSame(0, $result);
        self::assertCount(1, $output->logs);
        [$logTask, $message] = $output->logs[0];
        self::assertSame('task', $logTask);
        self::assertStringContainsString('IPC timeout while polling task task', $message);
        self::assertStringContainsString('Request timed out', $message);
        self::assertSame(TaskStatus::SUCCESS, $output->completed[0][1]);
    }

    public function testHandleStreamExceptionRemovesState(): void
    {
        $peer = new NativeIpcPeer();
        $executionFactory = new class implements ExecutionFactory {
            public function launch(string $commandName, string $taskId, array $options, int $remainingTasks, IpcHandlerRegistry $registry, ?callable $onException = null): TaskState
            {
                throw new RuntimeException('Not expected in this test.');
            }

            public function shutdown(): void
            {
            }
        };

        $orchestrator = new TaskOrchestrator(
            $peer,
            $executionFactory,
            $this->createProgressOutputFactory(),
            $this->createRegistryFactory(),
        );

        [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $session = $peer->createStreamSession($a, $a);

        $execution = new class($session) implements Execution {
            private int $exitCode = 255;

            public function __construct(private IpcSession $session)
            {
            }

            public function getSession(): IpcSession
            {
                return $this->session;
            }

            public function getExitCode(): ?int
            {
                return $this->exitCode;
            }

            public function kill(): array
            {
                return ['exitCode' => $this->exitCode, 'stdout' => '', 'stderr' => ''];
            }
        };

        $state = new TaskState('task', $execution);
        $states = ['task' => $state];
        $queue = $this->createQueue();
        $output = new RecordingProgressOutput();

        $orchestrator->handleStreamException(new InvalidStreamException($session), $states, $queue, $output);

        self::assertSame([], $states);
        self::assertCount(1, $output->logs);
        self::assertSame('task', $output->logs[0][0]);
        self::assertStringContainsString('Worker exited with code', $output->logs[0][1]);
        self::assertCount(1, $output->completed);
        self::assertSame(TaskStatus::ERROR, $output->completed[0][1]);
    }

    private function createQueue(): TaskTreeQueue
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): object
            {
                return new $id();
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $builder = new TaskTreeBuilder($container);
        $task = $builder->task('task', fn() => new class implements Task {
            public function execute(TaskCommunicator $comm): void
            {
            }
        });
        $root = $builder->group('root', [$task]);

        return new TaskTreeQueue(new TaskList($root), 1);
    }

    private function createProgressOutputFactory(): ProgressOutputFactory
    {
        return new class implements ProgressOutputFactory {
            public function create(TaskList $taskList, OutputInterface $output, IpcHandlerRegistry $registry, array $options): ProgressOutput
            {
                throw new RuntimeException('Not required for these tests.');
            }
        };
    }

    private function createRegistryFactory(): IpcHandlerRegistryFactory
    {
        return new class implements IpcHandlerRegistryFactory {
            public function create(): IpcHandlerRegistry
            {
                return new IpcHandlerRegistry();
            }
        };
    }
}

/**
 * @internal test helper
 */
final class RecordingProgressOutput implements ProgressOutput
{
    /** @var array<int, array{0: string, 1: string}> */
    public array $logs = [];

    /** @var array<int, array{0: string, 1: TaskStatus}> */
    public array $completed = [];

    public function onTaskStarted(TaskState $state): void
    {
    }

    public function onTaskUpdated(TaskState $state): void
    {
    }

    public function onTaskCompleted(TaskState $state): void
    {
        $this->completed[] = [$state->getTaskId(), $state->getStatus()];
    }

    public function log(TaskState $state, string $message): void
    {
        $this->logs[] = [$state->getTaskId(), $message];
    }

    public function render(): void
    {
    }
}
