<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Orchestrator\Output\TableOutput;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Tree\TaskNode;
use Multitron\Execution\Task;
use Multitron\Comms\TaskCommunicator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

final class TableOutputIntegrationTest extends TestCase
{
    private function createTaskList(): TaskList
    {
        $task = new TaskNode('task1', fn() => new class implements Task {
            public function execute(TaskCommunicator $comm): void {}
        });
        $root = new TaskNode('root', null, [$task]);
        return new TaskList($root);
    }

    public function testRenderNonInteractiveOutputsLogAndSummary(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        $table = new TableOutput($buffer, $list, false);

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
        $table = new TableOutput($buffer, $list, false);

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
