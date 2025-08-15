<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Orchestrator\Output\TableOutput;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Tests\Mocks\DummyTask;
use Multitron\Tree\TaskNode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class TableOutputIntegrationTest extends TestCase
{
    private function createTaskList(): TaskList
    {
        $task = new TaskNode('task1', fn() => new DummyTask());
        $root = new TaskNode('root', null, [$task]);
        return new TaskList($root);
    }

    public function testRenderNonInteractiveOutputsLogAndSummary(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        $table = new TableOutput($buffer, $list, false, TableOutputFactory::DEFAULT_LOW_MEMORY_WARNING);

        $state = new TaskState('task1');
        $table->onTaskStarted($state);
        $table->log($state, 'hello');
        $state->setStatus(TaskStatus::SUCCESS);
        $table->onTaskCompleted($state);
        unset($table);
        gc_collect_cycles();

        $out = $buffer->fetch();
        $this->assertStringContainsString('hello', $out);
        $this->assertStringContainsString('TOTAL', $out);
    }

    public function testDestructorOutputsSummaryWhenNonInteractive(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        $table = new TableOutput($buffer, $list, false, TableOutputFactory::DEFAULT_LOW_MEMORY_WARNING);

        $state = new TaskState('task1');
        $table->onTaskStarted($state);
        $state->setStatus(TaskStatus::SUCCESS);
        $table->onTaskCompleted($state);
        unset($table);
        gc_collect_cycles();

        $out = $buffer->fetch();
        $this->assertStringContainsString('TOTAL', $out);
    }
}

