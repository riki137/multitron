<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Orchestrator\TaskList;
use Multitron\Tree\TaskNode;
use Multitron\Execution\Task;
use Multitron\Comms\TaskCommunicator;
use PHPUnit\Framework\TestCase;

final class DummyTaskTL implements Task { public function execute(TaskCommunicator $c): void {} }

final class TaskListTest extends TestCase
{
    public function testGetHasAndIteration(): void
    {
        $child = new TaskNode('child', fn() => new DummyTaskTL());
        $root = new TaskNode('root', null, [$child]);
        $list = new TaskList($root);

        $this->assertTrue($list->has('child'));
        $node = $list->get('child');
        $this->assertNotNull($node);
        $this->assertSame('child', $node->id);

        $array = $list->toArray();
        $this->assertArrayHasKey('child', $array);

        $ids = [];
        foreach ($list as $id => $n) { $ids[] = $id; }
        sort($ids);
        $this->assertSame(['child'], $ids);
    }
}
