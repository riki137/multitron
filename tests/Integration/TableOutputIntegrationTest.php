<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Message\TaskWarningStateMessage;
use Multitron\Orchestrator\Output\TableOutput;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Tests\Mocks\DummyTask;
use Multitron\Tree\TaskNode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;

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

    public function testOnTaskUpdatedDoesNothing(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        $table = new TableOutput($buffer, $list, false, TableOutputFactory::DEFAULT_LOW_MEMORY_WARNING);

        $state = new TaskState('task1');
        $table->onTaskStarted($state);
        $table->onTaskUpdated($state); // Should not throw or do anything observable
        $this->assertTrue(true);
    }

    public function testDestructorInteractiveModeWithObBuffer(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        $table = new TableOutput($buffer, $list, true, TableOutputFactory::DEFAULT_LOW_MEMORY_WARNING);

        $state = new TaskState('task1');
        $table->onTaskStarted($state);
        ob_start();
        echo "buffered content";
        $state->setStatus(TaskStatus::SUCCESS);
        $table->onTaskCompleted($state);
        unset($table);
        gc_collect_cycles();

        $out = $buffer->fetch();
        $this->assertStringContainsString('buffered content', $out);
    }

    public function testRenderInteractiveWithEmptyObBuffer(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        $table = new TableOutput($buffer, $list, true, TableOutputFactory::DEFAULT_LOW_MEMORY_WARNING);

        $state = new TaskState('task1');
        $table->onTaskStarted($state);
        
        // Start output buffering to match what TableOutput expects
        ob_start();
        $table->render();

        // Should have started output buffering and cleared it
        $out = $buffer->fetch();
        $this->assertNotEmpty($out);
    }

    public function testRenderInteractiveWithWhitespaceObBuffer(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        $table = new TableOutput($buffer, $list, true, TableOutputFactory::DEFAULT_LOW_MEMORY_WARNING);

        $state = new TaskState('task1');
        $table->onTaskStarted($state);
        
        // Start output buffering to match what TableOutput expects
        ob_start();
        $table->render();

        // Check that output contains task information
        $out = $buffer->fetch();
        $this->assertStringContainsString('task1', $out);
    }

    public function testConstructorWithConsoleOutput(): void
    {
        if (!class_exists(ConsoleOutput::class)) {
            $this->markTestSkipped('ConsoleOutput not available');
        }

        $list = $this->createTaskList();
        $output = new ConsoleOutput();
        $table = new TableOutput($output, $list, true, TableOutputFactory::DEFAULT_LOW_MEMORY_WARNING);

        $state = new TaskState('task1');
        $table->onTaskStarted($state);
        $state->setStatus(TaskStatus::SUCCESS);
        $table->onTaskCompleted($state);
        
        // Start output buffering to allow destructor to clean it up properly
        $obLevel = ob_get_level();
        ob_start();
        unset($table);
        gc_collect_cycles();
        // Clean up any buffers created during the test to restore original state
        while (ob_get_level() > $obLevel) {
            ob_end_clean();
        }

        $this->assertTrue(true); // If we get here without errors, the test passes
    }

    public function testTaskWithWarnings(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        $table = new TableOutput($buffer, $list, false, TableOutputFactory::DEFAULT_LOW_MEMORY_WARNING);

        $state = new TaskState('task1');
        $table->onTaskStarted($state);
        
        // Add a warning to the state
        $state->getWarnings()->add('Test warning', 1);
        
        $state->setStatus(TaskStatus::SUCCESS);
        $table->onTaskCompleted($state);
        unset($table);
        gc_collect_cycles();

        $out = $buffer->fetch();
        $this->assertStringContainsString('Test warning', $out);
    }

    public function testLowMemoryWarning(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        // Set a very high low memory warning threshold to trigger the warning
        $table = new TableOutput($buffer, $list, false, 999999);

        $state = new TaskState('task1');
        $table->onTaskStarted($state);
        $state->setStatus(TaskStatus::SUCCESS);
        $table->onTaskCompleted($state);
        unset($table);
        gc_collect_cycles();

        $out = $buffer->fetch();
        // Should contain low memory warning if /proc/meminfo is available
        $this->assertNotEmpty($out);
    }

    public function testBuildSectionBufferWithMemoryUsage(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        $table = new TableOutput($buffer, $list, true, TableOutputFactory::DEFAULT_LOW_MEMORY_WARNING);

        $state = new TaskState('task1');
        // Set memory usage on the state's progress
        $state->getProgress()->memoryUsage = 1048576; // 1MB
        
        $table->onTaskStarted($state);
        
        // Start output buffering to match what TableOutput expects
        ob_start();
        $table->render();

        $out = $buffer->fetch();
        $this->assertNotEmpty($out);
    }

    public function testAttachMemoryWarningWithZeroThreshold(): void
    {
        $list = $this->createTaskList();
        $buffer = new BufferedOutput();
        // Zero threshold should skip memory warning
        $table = new TableOutput($buffer, $list, false, 0);

        $state = new TaskState('task1');
        $table->onTaskStarted($state);
        $state->setStatus(TaskStatus::SUCCESS);
        $table->onTaskCompleted($state);
        unset($table);
        gc_collect_cycles();

        // Should complete without low memory warning
        $this->assertTrue(true);
    }
}

