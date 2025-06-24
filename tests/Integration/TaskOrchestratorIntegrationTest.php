<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Execution\Execution;
use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tree\TaskNode;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeQueue;
use Multitron\Execution\Task;
use Multitron\Comms\TaskCommunicator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use StreamIpc\IpcPeer;
use StreamIpc\NativeIpcPeer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

final class TaskOrchestratorIntegrationTest extends TestCase
{
    public function testDoRunCompletesAllTasks(): void
    {
        $peer = new NativeIpcPeer();
        $execFactory = new class($peer) implements ExecutionFactory {
            public function __construct(private IpcPeer $peer) {}
            public function launch(string $commandName, string $taskId, array $options, int $remainingTasks, IpcHandlerRegistry $registry, ?callable $onException = null): \Multitron\Orchestrator\TaskState {
                [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                $session = $this->peer->createStreamSession($a, $a);
                $exec = new class($session) implements Execution {
                    private ?int $exitCode = null;
                    public function __construct(private \StreamIpc\IpcSession $session) {}
                    public function getSession(): \StreamIpc\IpcSession { return $this->session; }
                    public function getExitCode(): ?int { return $this->exitCode ??= 0; }
                    public function kill(): array { return ['exitCode' => $this->exitCode, 'stdout' => '', 'stderr' => '']; }
                };
                $state = new \Multitron\Orchestrator\TaskState($taskId, $exec);
                $registry->attach($state);
                return $state;
            }
            public function shutdown(): void {}
        };

        $registryFactory = new class implements IpcHandlerRegistryFactory {
            public function create(): IpcHandlerRegistry { return new IpcHandlerRegistry(); }
        };
        $tableFactory = new TableOutputFactory();
        $orchestrator = new TaskOrchestrator($peer, $execFactory, $tableFactory, $registryFactory);

        $container = new class implements ContainerInterface {
            public function get(string $id): object { return new $id(); }
            public function has(string $id): bool { return true; }
        };
        $builder = new TaskTreeBuilder($container);
        $a = $builder->task('A', fn() => new class implements Task { public function execute(TaskCommunicator $comm): void {} });
        $b = $builder->task('B', fn() => new class implements Task { public function execute(TaskCommunicator $comm): void {} }, [$a]);
        $root = $builder->group('root', [$a, $b]);
        $list = new TaskList($root);
        $queue = new TaskTreeQueue($list, 2);

        $inputDef = new InputDefinition([new InputOption(TaskOrchestrator::OPTION_UPDATE_INTERVAL, 'u', InputOption::VALUE_REQUIRED)]);
        $input = new ArrayInput(['--'.TaskOrchestrator::OPTION_UPDATE_INTERVAL => '0.01'], $inputDef);
        $output = new BufferedOutput();
        $registry = $registryFactory->create();
        $progress = $tableFactory->create($list, $output, $registry, ['interactive' => false]);

        $result = $orchestrator->doRun('demo', $input->getOptions(), $queue, $progress, $registry);

        $this->assertSame(0, $result);
        $this->assertFalse($queue->hasUnfinishedTasks());
    }

    public function testLogsFailureAndExitCode(): void
    {
        $peer = new NativeIpcPeer();
        $execFactory = new class($peer) implements ExecutionFactory {
            public function __construct(private IpcPeer $peer) {}
            public function launch(string $commandName, string $taskId, array $options, int $remainingTasks, IpcHandlerRegistry $registry, ?callable $onException = null): \Multitron\Orchestrator\TaskState {
                [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                $session = $this->peer->createStreamSession($a, $a);
                $exec = new class($session) implements Execution {
                    public function __construct(private \StreamIpc\IpcSession $session) {}
                    public function getSession(): \StreamIpc\IpcSession { return $this->session; }
                    public function getExitCode(): ?int { return 1; }
                    public function kill(): array { return ['exitCode' => 1, 'stdout' => 'junk', 'stderr' => 'err']; }
                };
                $state = new \Multitron\Orchestrator\TaskState($taskId, $exec);
                $registry->attach($state);
                return $state;
            }
            public function shutdown(): void {}
        };

        $registryFactory = new class implements IpcHandlerRegistryFactory {
            public function create(): IpcHandlerRegistry { return new IpcHandlerRegistry(); }
        };
        $tableFactory = new TableOutputFactory();
        $orchestrator = new TaskOrchestrator($peer, $execFactory, $tableFactory, $registryFactory);

        $container = new class implements ContainerInterface {
            public function get(string $id): object { return new $id(); }
            public function has(string $id): bool { return true; }
        };
        $builder = new TaskTreeBuilder($container);
        $task = $builder->task('A', fn() => new class implements Task { public function execute(TaskCommunicator $comm): void {} });
        $root = $builder->group('root', [$task]);
        $list = new TaskList($root);
        $queue = new TaskTreeQueue($list, 1);

        $inputDef = new InputDefinition([new InputOption(TaskOrchestrator::OPTION_UPDATE_INTERVAL, 'u', InputOption::VALUE_REQUIRED)]);
        $input = new ArrayInput(['--'.TaskOrchestrator::OPTION_UPDATE_INTERVAL => '0.01'], $inputDef);
        $output = new BufferedOutput();
        $registry = $registryFactory->create();
        $progress = $tableFactory->create($list, $output, $registry, ['interactive' => false]);

        $result = $orchestrator->doRun('demo', $input->getOptions(), $queue, $progress, $registry);

        $this->assertSame(1, $result);
        $out = $output->fetch();
        $this->assertStringContainsString('Worker exited with code 1', $out);
        $this->assertStringContainsString('STDOUT: junk', $out);
        $this->assertStringContainsString('STDERR: err', $out);
    }
}
