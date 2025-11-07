<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Tests\Mocks\AppContainer;
use Multitron\Tests\Mocks\DummyExecutionFactory;
use Multitron\Tests\Mocks\DummyIpcHandlerRegistryFactory;
use Multitron\Tests\Mocks\DummyTask;
use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeQueue;
use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

final class TaskOrchestratorIntegrationTest extends TestCase
{
    public function testDoRunCompletesAllTasks(): void
    {
        $peer = new NativeIpcPeer();
        $execFactory = new DummyExecutionFactory($peer);
        $registryFactory = new DummyIpcHandlerRegistryFactory();
        $tableFactory = new TableOutputFactory();
        $orchestrator = new TaskOrchestrator($peer, $execFactory, $tableFactory, $registryFactory);

        $builder = new TaskTreeBuilder(new AppContainer());
        $a = $builder->task('A', fn() => new DummyTask());
        $b = $builder->task('B', fn() => new DummyTask(), [$a]);
        $root = $builder->group('root', [$a, $b]);
        $list = new TaskList($root);
        $queue = new TaskTreeQueue($list, 2);

        $inputDef = new InputDefinition([new InputOption(TaskOrchestrator::OPTION_UPDATE_INTERVAL, 'u', InputOption::VALUE_REQUIRED)]);
        $input = new ArrayInput(['--' . TaskOrchestrator::OPTION_UPDATE_INTERVAL => '0.01'], $inputDef);
        $output = new BufferedOutput();
        $registry = $registryFactory->create();
        $progress = $tableFactory->create($list, $output, $registry, ['interactive' => false]);

        $result = $orchestrator->doRun('demo', $input->getOptions(), $queue, $progress, $registry);

        $this->assertSame(0, $result);
        $this->assertFalse($queue->hasUnfinishedTasks());
    }

    public function testLogsFailureAndExitCode(): void
    {
        $peer = new NativeIpcPeer();
        $execFactory = new DummyExecutionFactory($peer, 1, ['exitCode' => 1, 'stdout' => 'junk', 'stderr' => 'err']);
        $registryFactory = new DummyIpcHandlerRegistryFactory();
        $tableFactory = new TableOutputFactory();
        $orchestrator = new TaskOrchestrator($peer, $execFactory, $tableFactory, $registryFactory);

        $builder = new TaskTreeBuilder(new AppContainer());
        $task = $builder->task('A', fn() => new DummyTask());
        $root = $builder->group('root', [$task]);
        $list = new TaskList($root);
        $queue = new TaskTreeQueue($list, 1);

        $inputDef = new InputDefinition([new InputOption(TaskOrchestrator::OPTION_UPDATE_INTERVAL, 'u', InputOption::VALUE_REQUIRED)]);
        $input = new ArrayInput(['--' . TaskOrchestrator::OPTION_UPDATE_INTERVAL => '0.01'], $inputDef);
        $output = new BufferedOutput();
        $registry = $registryFactory->create();
        $progress = $tableFactory->create($list, $output, $registry, ['interactive' => false]);

        $result = $orchestrator->doRun('demo', $input->getOptions(), $queue, $progress, $registry);

        $this->assertSame(1, $result);
        $out = $output->fetch();
        $this->assertStringContainsString('Worker exited with code 1', $out);
    }
}

