<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Execution\ProcessExecution;
use StreamIpc\NativeIpcPeer;
use ReflectionProperty;

final class ProcessExecutionKillStreamsIntegrationTest extends AbstractIpcTestCase
{
    private string $workerScript;

    protected function setUp(): void
    {
        parent::setUp();
        $script = <<<'PHP'
use StreamIpc\NativeIpcPeer;

$peer = new NativeIpcPeer();
$peer->createStdioSession();
while (true) {
    $peer->tick(0.1);
}
PHP;
        $this->workerScript = $this->createWorkerScript($script);
        $_ENV['MULTITRON_SCRIPTNAME'] = $this->workerScript;
    }

    protected function tearDown(): void
    {
        unset($_ENV['MULTITRON_SCRIPTNAME']);
        parent::tearDown();
    }

    public function testKillHandlesClosedStreams(): void
    {
        $peer = new NativeIpcPeer();
        $execution = new ProcessExecution($peer);

        $procProp = new ReflectionProperty($execution, 'process');
        $procProp->setAccessible(true);
        $proc = $procProp->getValue($execution);

        $pipesProp = new ReflectionProperty($proc, 'pipes');
        $pipesProp->setAccessible(true);
        $pipes = $pipesProp->getValue($proc);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $result = $execution->kill();
        $this->assertArrayHasKey('exitCode', $result);
        $this->assertArrayHasKey('stdout', $result);
        $this->assertArrayHasKey('stderr', $result);
    }
}
