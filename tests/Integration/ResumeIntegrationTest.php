<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Execution\ExecutionFactory;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use Multitron\Orchestrator\Checkpoint;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Orchestrator\TaskState;
use Multitron\Tests\Mocks\AppContainer;
use Multitron\Tests\Mocks\DummyExecution;
use Multitron\Tests\Mocks\DummyIpcHandlerRegistryFactory;
use Multitron\Tests\Mocks\DummyTask;
use Multitron\Tree\TaskTreeBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StreamIpc\IpcPeer;
use StreamIpc\NativeIpcPeer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

final class ResumeIntegrationTest extends TestCase
{
    /** @var string[] */
    private array $launched = [];

    /** @var string[] */
    private array $failing = [];

    public function testFailedRunCanBeResumed(): void
    {
        // run 1: A succeeds, B fails, C (depends on B) is skipped
        $this->failing = ['B'];
        $cache = new MasterCacheServer();
        $cache->import(['fromA' => 42]);
        [$code, $out] = $this->run_($cache);

        $this->assertSame(1, $code);
        $this->assertMatchesRegularExpression('/To resume where it failed: php .*--resume ([0-9a-f]{16})$/m', $out);
        preg_match('/--resume ([0-9a-f]{16})/', $out, $m);
        $id = $m[1];
        $this->assertFileExists(Checkpoint::path($id));

        // run 2: only B and C run again, cache from run 1 is restored
        $this->failing = [];
        $this->launched = [];
        $cache = new MasterCacheServer();
        [$code, $out] = $this->run_($cache, $id);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Resuming: 1 task(s) already done', $out);
        $this->assertSame(['B', 'C'], $this->launched);
        $this->assertSame(['fromA' => 42], $cache->export());
        $this->assertFileDoesNotExist(Checkpoint::path($id));
    }

    public function testSuccessfulRunLeavesNoCheckpoint(): void
    {
        $before = glob(sys_get_temp_dir() . '/multitron/*.ckpt') ?: [];
        [$code, $out] = $this->run_(new MasterCacheServer());

        $this->assertSame(0, $code);
        $this->assertStringNotContainsString('--resume', $out);
        $this->assertSame($before, glob(sys_get_temp_dir() . '/multitron/*.ckpt') ?: []);
    }

    public function testResumeRejectsOtherCommand(): void
    {
        $checkpoint = Checkpoint::create('other');
        $checkpoint->save();
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage("belongs to command 'other'");
            $this->run_(new MasterCacheServer(), $checkpoint->id);
        } finally {
            $checkpoint->delete();
        }
    }

    public function testCheckpointIdCannotEscapeDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        Checkpoint::load('../../etc/passwd', 'demo');
    }

    public function testSaveRemovesOldCheckpoints(): void
    {
        $old = Checkpoint::create('demo');
        $old->save();
        touch(Checkpoint::path($old->id), time() - Checkpoint::MAX_AGE - 60);

        $new = Checkpoint::create('demo');
        $new->save();

        $this->assertFileDoesNotExist(Checkpoint::path($old->id));
        $this->assertFileExists(Checkpoint::path($new->id));
        $new->delete();
    }

    /**
     * @return array{int, string}
     */
    private function run_(MasterCacheServer $cache, ?string $resume = null): array
    {
        $peer = new NativeIpcPeer();
        $orchestrator = new TaskOrchestrator($peer, $this->executionFactory($peer), new TableOutputFactory(), new DummyIpcHandlerRegistryFactory(), $cache);

        $builder = new TaskTreeBuilder(new AppContainer());
        $a = $builder->task('A', fn() => new DummyTask());
        $b = $builder->task('B', fn() => new DummyTask(), [$a]);
        $c = $builder->task('C', fn() => new DummyTask(), [$b]);
        $list = new TaskList($builder->group('root', [$a, $b, $c]));

        $input = new ArrayInput(
            ['--update-interval' => '0.01', '--concurrency' => '1', '--interactive' => 'no'] + ($resume !== null ? ['--resume' => $resume] : []),
            new InputDefinition([
                new InputOption(TaskOrchestrator::OPTION_UPDATE_INTERVAL, null, InputOption::VALUE_REQUIRED),
                new InputOption(TaskOrchestrator::OPTION_CONCURRENCY, null, InputOption::VALUE_REQUIRED),
                new InputOption(TaskOrchestrator::OPTION_MEMORY_LIMIT, null, InputOption::VALUE_REQUIRED),
                new InputOption(TaskOrchestrator::OPTION_RESUME, null, InputOption::VALUE_REQUIRED),
                new InputOption(TableOutputFactory::OPTION_INTERACTIVE, null, InputOption::VALUE_REQUIRED),
            ])
        );
        $output = new BufferedOutput();
        $code = $orchestrator->run('demo', $list, $input, $output);
        return [$code, $output->fetch()];
    }

    private function executionFactory(IpcPeer $peer): ExecutionFactory
    {
        return new class ($peer, $this->launched, $this->failing) implements ExecutionFactory {
            /** @param string[] $launched @param string[] $failing */
            public function __construct(private IpcPeer $peer, private array &$launched, private array $failing)
            {
            }

            public function launch(string $commandName, string $taskId, array $options, int $remainingTasks, IpcHandlerRegistry $registry, ?callable $onException = null): TaskState
            {
                $this->launched[] = $taskId;
                [$a] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                $exitCode = in_array($taskId, $this->failing, true) ? 1 : 0;
                return new TaskState($taskId, new DummyExecution($this->peer->createStreamSession($a, $a), $exitCode, ['exitCode' => $exitCode, 'stdout' => '', 'stderr' => '']));
            }

            public function shutdown(): void
            {
            }
        };
    }
}
