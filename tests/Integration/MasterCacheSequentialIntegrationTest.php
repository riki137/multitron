<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Comms\MasterCacheClient;
use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;

final class MasterCacheSequentialIntegrationTest extends TestCase
{
    public function testWriteDepthPrecedence(): void
    {
        [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $peer = new NativeIpcPeer();
        $serverSession = $peer->createStreamSession($a, $a);
        $clientSession = $peer->createStreamSession($b, $b);

        $server = new MasterCacheServer();
        $serverSession->onRequest($server->handleRequest(...));

        $client = new MasterCacheClient($clientSession);

        $client->write([
            'a' => ['b' => ['deep' => 1], 'c' => 1],
            'x' => ['y' => 2],
        ])->await();

        $client->write([
            'a' => ['c' => 99],
            'd' => 4,
        ], 2)->await();

        $client->write([
            'a' => ['b' => ['deep' => 2]],
            'x' => ['z' => 5],
        ])->await();

        $client->write(['m' => 1], 1)->await();

        $result = $client->read([
            'a' => ['b' => ['deep'], 'c'],
            'x',
            'd',
            'm',
        ])->await();

        $this->assertSame(
            [
                'a' => ['b' => ['deep' => 2], 'c' => 99],
                'x' => ['y' => 2, 'z' => 5],
                'd' => 4,
                'm' => 1,
            ],
            $result,
        );

        $this->assertNull($client->readKey('missing')->await());

        $server->clear();
        $this->assertSame([], $client->read(['a', 'm'])->await());
    }
}
