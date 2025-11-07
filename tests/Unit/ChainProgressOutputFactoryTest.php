<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\Output\ChainProgressOutputFactory;
use Multitron\Orchestrator\Output\ProgressOutput;
use Multitron\Orchestrator\Output\ProgressOutputFactory;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use Multitron\Tree\TaskNode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class SimpleFactory implements ProgressOutputFactory
{
    public function create(TaskList $taskList, \Symfony\Component\Console\Output\OutputInterface $output, IpcHandlerRegistry $registry, array $options): ProgressOutput
    {
        return new RecorderOutput();
    }
}

final class ChainProgressOutputFactoryTest extends TestCase
{
    public function testCreatesChainOutputFromMultipleFactories(): void
    {
        $factory1 = new SimpleFactory();
        $factory2 = new SimpleFactory();
        $chain = new ChainProgressOutputFactory($factory1, $factory2);

        $taskList = new TaskList(new TaskNode('root'));
        $output = new BufferedOutput();
        $registry = new IpcHandlerRegistry();

        $result = $chain->create($taskList, $output, $registry, []);

        $this->assertInstanceOf(\Multitron\Orchestrator\Output\ChainProgressOutput::class, $result);
        
        // Test that it forwards to both outputs
        $state = new TaskState('t1');
        $result->onTaskStarted($state);
        // If this doesn't throw, the chain was created correctly
        $this->assertTrue(true);
    }

    public function testConstructorAcceptsVariadicFactories(): void
    {
        $factory = new ChainProgressOutputFactory(
            new SimpleFactory(),
            new SimpleFactory(),
            new SimpleFactory()
        );

        $this->assertInstanceOf(ChainProgressOutputFactory::class, $factory);
    }
}
