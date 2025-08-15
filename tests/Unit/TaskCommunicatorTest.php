<?php

namespace Multitron\Tests\Unit;

use Multitron\Comms\TaskCommunicator;
use Multitron\Tests\Mocks\DummyPeer;
use Multitron\Tests\Mocks\DummyTransport;
use PHPUnit\Framework\TestCase;
use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\IpcSession;
use StreamIpc\Message\LogMessage;

final class TaskCommunicatorTest extends TestCase
{
    private DummyTransport $transport;

    private IpcSession $session;

    private TaskCommunicator $comm;

    protected function setUp(): void
    {
        $peer = new DummyPeer();
        $this->transport = new DummyTransport();
        $this->session = $peer->make($this->transport);
        $this->comm = new TaskCommunicator($this->session, ['foo' => 'bar']);
    }

    public function testGetOptions(): void
    {
        $this->assertSame('bar', $this->comm->getOption('foo'));
        $this->assertNull($this->comm->getOption('missing'));
        $this->assertSame(['foo' => 'bar'], $this->comm->getOptions());
    }

    public function testLogSendsMessageViaSession(): void
    {
        $this->comm->log('hi', 'warning');
        $this->assertCount(1, $this->transport->sent);
        $msg = $this->transport->sent[0];
        $this->assertInstanceOf(LogMessage::class, $msg);
        $this->assertSame('hi', $msg->message);
        $this->assertSame('warning', $msg->level);
    }

    public function testRequestReturnsPromise(): void
    {
        $promise = $this->comm->request(new LogMessage('req'));
        $this->assertInstanceOf(ResponsePromise::class, $promise);
        $this->assertCount(1, $this->transport->sent);
    }

    public function testNotifySendsMessage(): void
    {
        $msg = new LogMessage('note');
        $this->comm->notify($msg);
        $this->assertSame([$msg], $this->transport->sent);
    }

    public function testShutdownFlushesProgress(): void
    {
        $this->comm->shutdown();
        $this->assertNotEmpty($this->transport->sent);
    }
}
