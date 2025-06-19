<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Execution\ProcessExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\TaskState;
use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;

final class ProcessExecutionFactoryIntegrationTest extends TestCase
{
    private string $workerScript;

    protected function setUp(): void
    {
        parent::setUp();
        $autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoload) {
            $this->markTestSkipped('Could not locate vendor autoload');
        }
        $this->workerScript = sys_get_temp_dir() . '/worker_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
require %s;
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
        file_put_contents($this->workerScript, sprintf($script, var_export($autoload, true)));
        $_ENV['MULTITRON_SCRIPTNAME'] = $this->workerScript;
    }

    protected function tearDown(): void
    {
        unset($_ENV['MULTITRON_SCRIPTNAME']);
        @unlink($this->workerScript);
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
