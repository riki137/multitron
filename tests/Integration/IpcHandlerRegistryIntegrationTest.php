<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Comms\MasterCacheClient;
use Multitron\Comms\ProgressClient;
use Multitron\Execution\Execution;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use Multitron\Execution\Handler\ProgressServer;
use Multitron\Orchestrator\TaskState;
use PHPUnit\Framework\TestCase;
use StreamIpc\IpcSession;
use StreamIpc\NativeIpcPeer;

final class IpcHandlerRegistryIntegrationTest extends TestCase
{
    private function createExecution(IpcSession $session): Execution
    {
        return new class($session) implements Execution {
            public function __construct(private IpcSession $session)
            {
            }

            public function getSession(): IpcSession
            {
                return $this->session;
            }

            public function getExitCode(): ?int
            {
                return null;
            }

            public function kill(): array
            {
                return [];
            }
        };
    }

    public function testHandlersForwardMessagesAndRequests(): void
    {
        [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $peer = new NativeIpcPeer();
        $serverSession = $peer->createStreamSession($a, $a);
        $clientSession = $peer->createStreamSession($b, $b);

        $registry = new IpcHandlerRegistry();
        $registry->onRequest((new MasterCacheServer())->handleRequest(...));
        $registry->onMessage((new ProgressServer())->handleProgress(...));

        $state = new TaskState('t1', $this->createExecution($serverSession));
        $registry->attach($state);

        $cache = new MasterCacheClient($clientSession);
        $cache->write(['foo' => ['bar' => 1]])->await();
        $this->assertSame(['foo' => ['bar' => 1]], $cache->read(['foo'])->await());

        $progress = new ProgressClient($clientSession, 0.0);
        $progress->setTotal(2);
        $progress->addDone();
        $peer->tick(0.01);

        $this->assertSame(1, $state->getProgress()->done);

        $progress->addWarning('bad!');
        $progress->shutdown();
        $peer->tick(0.01);

        $warnings = iterator_to_array($state->getWarnings()->fetchAll());
        $this->assertSame([
            ['messages' => ['bad!'], 'count' => 1],
        ], $warnings);
    }
}
