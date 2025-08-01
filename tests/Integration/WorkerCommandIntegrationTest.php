<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Execution\ProcessExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Tests\Integration\AbstractIpcTestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use StreamIpc\NativeIpcPeer;

final class WorkerCommandIntegrationTest extends AbstractIpcTestCase
{
    private string $script;

    protected function setUp(): void
    {
        parent::setUp();
        $script = <<<'PHP'
require %s;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Console\TaskCommand;
use Multitron\Execution\Task;
use Multitron\Comms\TaskCommunicator;
use Multitron\Tree\TaskTreeBuilder;

class AppContainer implements ContainerInterface {
    public function get(string $id): object { return new $id(); }
    public function has(string $id): bool { return class_exists($id); }
}

#[AsCommand('app:demo')]
class DemoCommand extends TaskCommand {
    public function getNodes(TaskTreeBuilder $b): array {
        return [
            $b->task('task', fn() => new class implements Task { public function execute(TaskCommunicator $c): void {} }),
        ];
    }
}

$factory = new MultitronFactory(new AppContainer());
$app = new Application();
$app->add($factory->getWorkerCommand());
$app->add(new DemoCommand($factory->getTaskCommandDeps()));
$app->run();
PHP;
        $this->script = $this->createWorkerScript(sprintf($script, var_export(self::$autoloadPath, true)));
        $_ENV['MULTITRON_SCRIPTNAME'] = $this->script;
    }

    protected function tearDown(): void
    {
        unset($_ENV['MULTITRON_SCRIPTNAME']);
        parent::tearDown();
    }

    public function testWorkerCommandProcessesTask(): void
    {
        $peer = new NativeIpcPeer();
        $factory = new ProcessExecutionFactory($peer, 1, 1.0);
        $registry = new IpcHandlerRegistry();
        $state = $factory->launch('app:demo', 'task', [], 0, $registry);
        $execution = $state->getExecution();
        $this->assertNotNull($execution);
        for ($i = 0; $i < 50 && $execution->getExitCode() === null; $i++) {
            usleep(100000);
        }
        $this->assertSame(0, $execution->getExitCode());
        $factory->shutdown();
    }
}
