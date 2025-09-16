<?php
declare(strict_types=1);

namespace Multitron\Orchestrator {
    final class IniSetTestHook
    {
        public static bool $forceFail = false;
    }

    function ini_set(string $option, string $value): string|false
    {
        if ($option === 'memory_limit' && IniSetTestHook::$forceFail) {
            return false;
        }

        return \ini_set($option, $value);
    }
}

namespace Multitron\Tests\Integration {
    use Multitron\Execution\ExecutionFactory;
    use Multitron\Execution\Handler\IpcHandlerRegistryFactory;
    use Multitron\Orchestrator\Output\ProgressOutputFactory;
    use Multitron\Orchestrator\TaskList;
    use Multitron\Orchestrator\TaskOrchestrator;
    use Multitron\Tree\TaskNode;
    use PHPUnit\Framework\TestCase;
    use RuntimeException;
    use StreamIpc\NativeIpcPeer;
    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Input\InputDefinition;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\BufferedOutput;

    final class TaskCommandMemoryLimitIntegrationTest extends TestCase
    {
        protected function tearDown(): void
        {
            \Multitron\Orchestrator\IniSetTestHook::$forceFail = false;
        }

        public function testRunThrowsWhenMemoryLimitCannotBeSet(): void
        {
            \Multitron\Orchestrator\IniSetTestHook::$forceFail = true;

            $peer = new NativeIpcPeer();
            $executionFactory = $this->createMock(ExecutionFactory::class);
            $executionFactory->expects($this->never())->method('launch');

            $progressFactory = $this->createMock(ProgressOutputFactory::class);
            $progressFactory->expects($this->never())->method('create');

            $registryFactory = $this->createMock(IpcHandlerRegistryFactory::class);
            $registryFactory->expects($this->never())->method('create');

            $orchestrator = new TaskOrchestrator($peer, $executionFactory, $progressFactory, $registryFactory);
            $taskList = new TaskList(new TaskNode('root'));

            $definition = new InputDefinition([
                new InputOption(TaskOrchestrator::OPTION_MEMORY_LIMIT, 'm', InputOption::VALUE_REQUIRED, '', TaskOrchestrator::DEFAULT_MEMORY_LIMIT),
                new InputOption(TaskOrchestrator::OPTION_CONCURRENCY, 'c', InputOption::VALUE_REQUIRED),
                new InputOption(TaskOrchestrator::OPTION_UPDATE_INTERVAL, 'u', InputOption::VALUE_REQUIRED, '', TaskOrchestrator::DEFAULT_UPDATE_INTERVAL),
            ]);
            $input = new ArrayInput([
                '--' . TaskOrchestrator::OPTION_MEMORY_LIMIT => '512M',
                '--' . TaskOrchestrator::OPTION_CONCURRENCY => '1',
                '--' . TaskOrchestrator::OPTION_UPDATE_INTERVAL => (string) TaskOrchestrator::DEFAULT_UPDATE_INTERVAL,
            ], $definition);
            $output = new BufferedOutput();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Failed to set memory limit to 512M.');

            $orchestrator->run('demo', $taskList, $input, $output);
        }
    }
}
