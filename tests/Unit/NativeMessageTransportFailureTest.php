<?php
namespace StreamIpc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StreamIpc\Serialization\NativeMessageSerializer;
use StreamIpc\Transport\NativeMessageTransport;
use StreamIpc\Transport\StreamClosedException;
use StreamIpc\Message\LogMessage;

final class NativeMessageTransportFailureTest extends TestCase
{
    public function testSendFailsWhenWriteStreamClosed(): void
    {
        [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $transport = new NativeMessageTransport($a, [$a], new NativeMessageSerializer());
        fclose($b); // close peer to trigger broken pipe on write

        $this->expectException(StreamClosedException::class);
        try {
            $transport->send(new LogMessage('fail', 'info'));
        } finally {
            fclose($a);
        }
    }

    public function testReadFromUnknownStreamThrows(): void
    {
        $write = fopen('php://memory', 'r+');
        $read  = fopen('php://memory', 'r+');
        $transport = new NativeMessageTransport($write, [$read], new NativeMessageSerializer());

        $other = fopen('php://memory', 'r+');
        $this->expectException(\LogicException::class);
        try {
            $transport->readFromStream($other);
        } finally {
            fclose($other);
            fclose($write);
            fclose($read);
        }
    }
}
