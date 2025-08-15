<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Orchestrator\TaskList;
use Multitron\Tests\Mocks\DummyTask;
use Multitron\Tree\TaskNode;
use PHPUnit\Framework\TestCase;

final class TaskListTest extends TestCase
{
    public function testGetHasAndIteration(): void
    {
        $child = new TaskNode('child', fn() => new DummyTask());
        $root = new TaskNode('root', null, [$child]);
        $list = new TaskList($root);

        $this->assertTrue($list->has('child'));
        $node = $list->get('child');
        $this->assertNotNull($node);
        $this->assertSame('child', $node->id);

        $array = $list->toArray();
        $this->assertArrayHasKey('child', $array);

        $ids = [];
        foreach ($list as $id => $n) {
            $ids[] = $id;
        }
        sort($ids);
        $this->assertSame(['child'], $ids);
    }
}

