<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Execution\ProcessExecutionFactory;
use Multitron\Orchestrator\TaskState;
use Multitron\Tests\Integration\AbstractIpcTestCase;
use StreamIpc\NativeIpcPeer;

final class ProcessExecutionFactoryIntegrationTest extends AbstractIpcTestCase
{
    private string $workerScript;

    protected function setUp(): void
    {
        parent::setUp();
        $script = <<<'PHP'
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\Message;
use StreamIpc\Message\LogMessage;
use Multitron\Message\ContainerLoadedMessage;
use Multitron\Message\StartTaskMessage;

$peer = new NativeIpcPeer();
$session = $peer->createStdioSession();
$started = false;
$session->onRequest(function (Message $m) use (&$started) {
    if ($m instanceof ContainerLoadedMessage) { return $m; }
    if ($m instanceof StartTaskMessage) { $started = true; return new LogMessage('ok'); }
    return null;
});
while (!$started) {
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

    public function testLaunchRunsWorkerProcess(): void
    {
        $peer = new NativeIpcPeer();
        $factory = new ProcessExecutionFactory($peer, 1, 1.0);
        $registry = new IpcHandlerRegistry();
        $state = $factory->launch('demo', 'task1', [], 0, $registry);
        $this->assertInstanceOf(TaskState::class, $state);
        $execution = $state->getExecution();
        $this->assertNotNull($execution);
        for ($i = 0; $i < 50 && $execution->getExitCode() === null; $i++) {
            usleep(100000);
        }
        $this->assertSame(0, $execution->getExitCode());
        $factory->shutdown();
    }
}
