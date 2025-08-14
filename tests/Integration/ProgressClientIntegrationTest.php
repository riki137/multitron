<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Comms\ProgressClient;
use Multitron\Message\TaskProgress;
use Multitron\Message\TaskWarningStateMessage;
use PHPUnit\Framework\TestCase;
use StreamIpc\Message\Message;
use StreamIpc\NativeIpcPeer;

final class ProgressClientIntegrationTest extends TestCase
{
    public function testProgressAndWarningsFlushedOnShutdown(): void
    {
        [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $peer = new NativeIpcPeer();
        $serverSession = $peer->createStreamSession($a, $a);
        $clientSession = $peer->createStreamSession($b, $b);

        $received = [];
        $serverSession->onMessage(function (Message $msg) use (&$received) {
            $received[] = $msg;
        });

        $client = new ProgressClient($clientSession, 0.1);
        $client->setTotal(3);
        $peer->tick(0.01);
        $this->assertCount(1, $received);

        $client->addDone();
        $peer->tick(0.01);
        $this->assertCount(1, $received);

        usleep(120000);
        $client->addDone();
        $peer->tick(0.01);
        $this->assertCount(2, $received);

        usleep(120000);
        $client->addOccurrence('foo');
        $peer->tick(0.01);
        $this->assertCount(3, $received);

        $client->addWarning('bad! 1');
        $client->addWarning('bad! 1');
        $client->addWarning('bad!2');

        $client->shutdown();
        $peer->tick(0.01);

        $this->assertGreaterThanOrEqual(4, count($received));
        $this->assertInstanceOf(TaskProgress::class, $received[0]);
        $this->assertInstanceOf(TaskProgress::class, $received[count($received) - 2]);
        $this->assertNotNull($received[0]->memoryUsage);

        $last = $received[count($received) - 1];
        $this->assertInstanceOf(TaskWarningStateMessage::class, $last);
        $this->assertArrayHasKey('bad', $last->warnings);
        $this->assertSame(['bad! 1', 'bad!2'], $last->warnings['bad']);
        $this->assertSame(['bad' => 3], $last->warningCount);
    }
}
