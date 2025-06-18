<?php
namespace Multitron\Tests\Unit;

use Multitron\Comms\TaskCommunicator;
use PHPUnit\Framework\TestCase;
use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\Message\Message;
use StreamIpc\Message\LogMessage;
use StreamIpc\Transport\MessageTransport;

class DummyTransport implements MessageTransport
{
    public array $sent = [];

    public function send(Message $message): void { $this->sent[] = $message; }
    public function getReadStreams(): array { return []; }
    public function readFromStream($stream): array { return []; }
}

class DummyPeer extends IpcPeer
{
    public function make(MessageTransport $t): IpcSession { return $this->createSession($t); }
    public function tick(?float $timeout = null): void {}
}

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
}

