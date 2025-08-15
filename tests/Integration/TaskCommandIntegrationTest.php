<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Comms\NativeIpcAdapter;
use Multitron\Comms\TaskCommunicator;
use Multitron\Console\TaskCommand;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use Multitron\Execution\Execution;
use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Execution\Task;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Orchestrator\TaskState;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeBuilderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\NativeIpcPeer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class TaskCommandIntegrationTest extends TestCase
{
    private function createDeps(IpcPeer $peer): TaskCommandDeps
    {
        $execFactory = new class($peer) implements ExecutionFactory {
            public function __construct(private IpcPeer $peer)
            {
            }

            public function launch(string $commandName, string $taskId, array $options, int $remaining, IpcHandlerRegistry $registry, ?callable $onException = null): TaskState
            {
                [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                $session = $this->peer->createStreamSession($a, $a);
                $exec = new class($session) implements Execution {
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
                        return ['exitCode' => 0,'stdout' => '','stderr' => ''];
                    }
                };
                $state = new TaskState($taskId, $exec);
                $registry->attach($state);
                return $state;
            }

            public function shutdown(): void
            {
            }
        };
        $registryFactory = new class implements IpcHandlerRegistryFactory {
            public function create(): IpcHandlerRegistry
            {
                return new IpcHandlerRegistry();
            }
        };
        $builderFactory = new TaskTreeBuilderFactory(new class implements ContainerInterface {
            public function get(string $id): object
            {
                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }
        });
        $outputFactory = new TableOutputFactory();
        $orchestrator = new TaskOrchestrator($peer, $execFactory, $outputFactory, $registryFactory);
        return new TaskCommandDeps($builderFactory, $orchestrator);
    }

    public function testGetTaskListFiltersByPattern(): void
    {
        $peer = new NativeIpcPeer();
        $deps = $this->createDeps($peer);
        $command = new #[AsCommand('demo')] class($deps) extends TaskCommand {
            public function getNodes(TaskTreeBuilder $builder): array
            {
                return [

                    $builder->task('first', fn() => new class implements Task { public function execute(TaskCommunicator $c): void
                    {
                    }
                    }),

                    $builder->task('second', fn() => new class implements Task { public function execute(TaskCommunicator $c): void
                    {
                    }
                    }),
                ];
            }
        };

        $list = $command->getTaskList(new ArrayInput(['pattern' => 'first'], $command->getDefinition()));
        $nodes = $list->toArray();
        $this->assertCount(1, $nodes);
        $this->assertArrayHasKey('first', $nodes);
    }

    public function testExecuteFailsWhenWorkerMissing(): void
    {
        $peer = new NativeIpcPeer();
        $deps = $this->createDeps($peer);
        $command = new #[AsCommand('demo')] class($deps) extends TaskCommand {
            public function getNodes(TaskTreeBuilder $builder): array
            {
                return [];
            }
        };

        $app = new Application();
        $app->add($command);

        $this->expectException(RuntimeException::class);
        $command->run(new ArrayInput([], $command->getDefinition()), new BufferedOutput());
    }

    public function testExecuteRunsTasks(): void
    {
        $peer = new NativeIpcPeer();
        $adapter = new NativeIpcAdapter($peer);
        $deps = $this->createDeps($peer);
        $command = new #[AsCommand('demo')] class($deps) extends TaskCommand {
            public function getNodes(TaskTreeBuilder $builder): array
            {
                return [

                    $builder->task('t', fn() => new class implements Task { public function execute(TaskCommunicator $c): void
                    {
                    }
                    }),
                ];
            }
        };

        $app = new Application();
        $worker = new WorkerCommand($adapter);
        $app->add($worker);
        $app->add($command);

        $result = $command->run(new ArrayInput([], $command->getDefinition()), new BufferedOutput());
        $this->assertSame(0, $result);
    }
}
