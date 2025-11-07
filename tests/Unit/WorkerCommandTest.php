<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Comms\IpcAdapter;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use Multitron\Execution\ExecutionFactory;
use Multitron\Message\ContainerLoadedMessage;
use Multitron\Message\StartTaskMessage;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tests\Mocks\AppContainer;
use Multitron\Tests\Mocks\DemoCommand;
use Multitron\Tests\Mocks\DummyExecutionFactory;
use Multitron\Tests\Mocks\DummyIpcHandlerRegistryFactory;
use Multitron\Tests\Mocks\DummyPeer;
use Multitron\Tests\Mocks\DummyTransport;
use Multitron\Tree\TaskTreeBuilderFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StreamIpc\Envelope\RequestEnvelope;
use StreamIpc\Envelope\ResponseEnvelope;
use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\Message\LogMessage;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Helper peer that dispatches a StartTaskMessage on tick.
 */
class TestWorkerPeer extends IpcPeer
{
    private ?IpcSession $session = null;
    private bool $dispatched = false;
    private DummyTransport $transport;

    public function __construct(private StartTaskMessage $start)
    {
        parent::__construct();
    }

    public function create(): IpcSession
    {
        $this->transport = new DummyTransport();
        $this->session = $this->createSession($this->transport);
        return $this->session;
    }

    public function getTransport(): DummyTransport
    {
        return $this->transport;
    }

    public function tick(?float $timeout = null): void
    {
        if ($this->dispatched || $this->session === null) {
            return;
        }
        $this->dispatched = true;
        $this->session->dispatch(new RequestEnvelope('1', new ContainerLoadedMessage()));
        $this->session->dispatch(new RequestEnvelope('2', $this->start));
    }
}

/**
 * IPC adapter wired with TestWorkerPeer.
 */
class TestIpcAdapter implements IpcAdapter
{
    private TestWorkerPeer $peer;

    public function __construct(StartTaskMessage $start)
    {
        $this->peer = new TestWorkerPeer($start);
    }

    public function createWorkerSession(?string $connection): IpcSession
    {
        return $this->peer->create();
    }

    public function getPeer(): IpcPeer
    {
        return $this->peer;
    }

    public function createExecutionFactory(?int $processBufferSize, float $timeout): ExecutionFactory
    {
        return new DummyExecutionFactory($this->getPeer());
    }
}

final class WorkerCommandTest extends TestCase
{
    private function createDeps(): TaskCommandDeps
    {
        $peer = new DummyPeer();
        $execFactory = new DummyExecutionFactory($peer);
        $registryFactory = new DummyIpcHandlerRegistryFactory();
        $builderFactory = new TaskTreeBuilderFactory(new AppContainer());
        $outputFactory = new TableOutputFactory();
        $orchestrator = new TaskOrchestrator($peer, $execFactory, $outputFactory, $registryFactory);
        return new TaskCommandDeps($builderFactory, $orchestrator);
    }

    public function testExecuteFailsWhenApplicationMissing(): void
    {
        $start = new StartTaskMessage('cmd', 'task', [TaskOrchestrator::OPTION_MEMORY_LIMIT => '512M']);
        $adapter = new TestIpcAdapter($start);
        $worker = new WorkerCommand($adapter);

        $tester = new CommandTester($worker);
        $this->expectException(RuntimeException::class);
        $tester->execute([]);
    }

    public function testExecuteFailsWhenCommandNotTaskCommand(): void
    {
        $start = new StartTaskMessage('foo', 'task', [TaskOrchestrator::OPTION_MEMORY_LIMIT => '512M']);
        $adapter = new TestIpcAdapter($start);
        $worker = new WorkerCommand($adapter);

        $app = new Application();
        $app->add($worker);
        $app->add(new Command('foo'));

        $tester = new CommandTester($worker);
        $this->expectException(RuntimeException::class);
        $tester->execute([]);
    }

    public function testExecuteFailsWhenTaskNotFound(): void
    {
        $start = new StartTaskMessage('app:demo', 'missing', [TaskOrchestrator::OPTION_MEMORY_LIMIT => '512M']);
        $adapter = new TestIpcAdapter($start);
        $worker = new WorkerCommand($adapter);

        $app = new Application();
        $app->add($worker);
        $demo = new DemoCommand($this->createDeps());
        $app->add($demo);

        $tester = new CommandTester($worker);
        $this->expectException(RuntimeException::class);
        $tester->execute([]);
    }

    public function testExecuteRunsTaskSuccessfully(): void
    {
        $start = new StartTaskMessage('app:demo', 'task', [TaskOrchestrator::OPTION_MEMORY_LIMIT => '512M']);
        $adapter = new TestIpcAdapter($start);
        $worker = new WorkerCommand($adapter);

        $app = new Application();
        $app->add($worker);
        $demo = new DemoCommand($this->createDeps());
        $app->add($demo);

        $oldLimit = ini_get('memory_limit');
        $tester = new CommandTester($worker);
        try {
            $result = $tester->execute([]);
            /** @var TestWorkerPeer $peer */
            $peer = $adapter->getPeer();
            $sent = $peer->getTransport()->sent;
            $foundContainer = false;
            $foundLog = false;
            foreach ($sent as $envelope) {
                if (!$envelope instanceof ResponseEnvelope) {
                    continue;
                }
                if ($envelope->response instanceof ContainerLoadedMessage) {
                    $foundContainer = true;
                }
                if ($envelope->response instanceof LogMessage && $envelope->response->message === 'Task started: task') {
                    $foundLog = true;
                }
            }
            $this->assertTrue($foundContainer);
            $this->assertTrue($foundLog);
            $this->assertSame('512M', ini_get('memory_limit'));
            $this->assertSame(Command::SUCCESS, $result);
        } finally {
            ini_set('memory_limit', $oldLimit);
        }
    }
}

