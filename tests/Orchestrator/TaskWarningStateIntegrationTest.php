<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Handler\ProgressServer;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskWarningState;
use Multitron\Message\TaskWarningStateMessage;
use StreamIpc\IpcPeer;
use StreamIpc\Message\Message;
use Multitron\Tests\Fixtures\FakeTransport;
use PHPUnit\Framework\TestCase;
use StreamIpc\IpcSession;

/**
 * Simple peer used in tests to expose the protected {@see IpcPeer::createSession}
 * method.
 */
class TestPeer extends IpcPeer
{
    public function createFakeSession(FakeTransport $transport): IpcSession
    {
        return $this->createSession($transport);
    }

    public function tick(?float $timeout = null): void
    {
        // no-op for tests
    }
}

final class TaskWarningStateIntegrationTest extends TestCase
{
    public function testClientAggregatesWarnings(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);
        $comm = new TaskCommunicator($session, []);

        $progress = $comm->progress();
        $progress->addWarning('first');
        $progress->addWarning('first');
        $progress->setWarning('second', 3);
        $progress->addWarning('second');

        $comm->shutdown();

        $this->assertCount(2, $transport->sent);
        $this->assertInstanceOf(TaskWarningStateMessage::class, $transport->sent[1]);

        /** @var TaskWarningStateMessage $msg */
        $msg = $transport->sent[1];
        $helper = new TaskWarningState();
        $k1 = $helper->warningKey('first');
        $k2 = $helper->warningKey('second');

        $this->assertSame(['first'], $msg->warnings[$k1]);
        $this->assertSame(2, $msg->warningCount[$k1]);
        $this->assertSame(['second'], $msg->warnings[$k2]);
        $this->assertSame(4, $msg->warningCount[$k2]);
    }

    public function testWarningMessageUpdatesServerState(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);
        $comm = new TaskCommunicator($session, []);
        $server = new ProgressServer();
        $state = new TaskState('t');

        $session->onMessage(fn(Message $m) => $server->handleProgress($m, $state));

        $comm->progress()->addWarning('warn');
        $comm->shutdown();

        foreach ($transport->sent as $msg) {
            $session->dispatch($msg);
        }

        $warnings = iterator_to_array($state->getWarnings()->fetchWarnings());
        $this->assertCount(1, $warnings);
        $this->assertSame(['warn'], $warnings[0]['messages']);
        $this->assertSame(1, $warnings[0]['count']);
    }

    public function testWarningLimitEnforced(): void
    {
        $state = new TaskWarningState();
        for ($i = 0; $i < TaskWarningState::WARNING_LIMIT + 1; $i++) {
            $state->addWarning('notice ' . $i, 1);
        }

        $key = $state->warningKey('notice 0');
        $this->assertCount(TaskWarningState::WARNING_LIMIT, $state->toMessage()->warnings[$key]);
    }
}
