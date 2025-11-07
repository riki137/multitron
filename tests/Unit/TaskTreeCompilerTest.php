<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Tests\Mocks\DummyTask;
use Multitron\Tree\TaskNode;
use Multitron\Tree\TaskTreeCompiler;
use PHPUnit\Framework\TestCase;

final class TaskTreeCompilerTest extends TestCase
{
    public function testTagDependencyExpansion(): void
    {
        $t1 = new TaskNode('first', fn() => new DummyTask(), tags: ['group']);
        $t2 = new TaskNode('second', fn() => new DummyTask(), dependencies: ['group']);
        $root = new TaskNode('root', null, [$t1, $t2]);

        $compiled = (new TaskTreeCompiler())->compile($root);

        $this->assertArrayHasKey('second', $compiled);
        $this->assertSame(['first'], $compiled['second']->dependencies);
    }
}

