<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Tree\TaskNode;
use Multitron\Tree\PatternTaskNodeFactory;
use Multitron\Tree\TaskTreeCompiler;
use Multitron\Execution\Task;
use Multitron\Comms\TaskCommunicator;
use PHPUnit\Framework\TestCase;

final class PatternTaskNodeFactoryTest extends TestCase
{
    public function testFilteringAndDependencyAdjustment(): void
    {
        $t1 = new TaskNode('alpha', fn() => new PtnDummyTask());
        $t2 = new TaskNode('beta', fn() => new PtnDummyTask(), [], ['alpha']);
        $t3 = new TaskNode('gamma', fn() => new PtnDummyTask(), [], ['beta']);

        $root = PatternTaskNodeFactory::create('filter', 'beta,gamma', [$t1, $t2, $t3]);
        $compiled = (new TaskTreeCompiler())->compile($root);

        $ids = array_keys($compiled);
        sort($ids);
        $this->assertSame(['alpha', 'beta', 'gamma'], $ids);
        $this->assertSame(['alpha'], $compiled['beta']->dependencies);
        $this->assertSame(['beta'], $compiled['gamma']->dependencies);
        $this->assertSame([], $compiled['alpha']->dependencies);
    }
}

final class PtnDummyTask implements Task
{
    public function execute(TaskCommunicator $comm): void {}
}
