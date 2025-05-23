<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskQueue;
use Multitron\Tree\ClosureTaskNode;
use Multitron\Tree\SimpleTaskGroupNode;
use Multitron\Tree\TaskTreeBuilderFactory;
use Multitron\Tree\TaskLeafNode;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use LogicException;

final class TaskQueueIntegrationTest extends TestCase
{
    private function createContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id) { return null; }
            public function has(string $id): bool { return false; }
        };
    }

    private function createDummyTask(): Task
    {
        return new class implements Task {
            public function execute(TaskCommunicator $comm): void {}
        };
    }

    public function testQueueRespectsDependenciesAndConcurrency(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        $task1 = new ClosureTaskNode('task1', fn() => $this->createDummyTask());
        $task2 = new ClosureTaskNode('task2', fn() => $this->createDummyTask(), ['task1']);
        $task3 = new ClosureTaskNode('task3', fn() => $this->createDummyTask(), ['task2']);

        $root = new SimpleTaskGroupNode('root', [$task1, $task2, $task3]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 1);

        $next = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $next);
        $this->assertSame('task1', $next->getId());
        $this->assertTrue($queue->getNextTask()); // concurrency limit reached
        $queue->completeTask('task1');

        $next = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $next);
        $this->assertSame('task2', $next->getId());
        $queue->completeTask('task2');

        $next = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $next);
        $this->assertSame('task3', $next->getId());
        $queue->completeTask('task3');

        $this->assertFalse($queue->getNextTask());
    }

    public function testFailTaskSkipsDependents(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        $task1 = new ClosureTaskNode('task1', fn() => $this->createDummyTask());
        $task2 = new ClosureTaskNode('task2', fn() => $this->createDummyTask(), ['task1']);
        $task3 = new ClosureTaskNode('task3', fn() => $this->createDummyTask(), ['task2']);

        $root  = new SimpleTaskGroupNode('root', [$task1, $task2, $task3]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 2);

        $next = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $next);
        $this->assertSame('task1', $next->getId());
        $queue->completeTask('task1');

        $next = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $next);
        $this->assertSame('task2', $next->getId());

        $skipped = $queue->failTask('task2');
        $this->assertSame(['task3'], $skipped);
        $this->assertFalse($queue->getNextTask());
    }

    public function testMultipleConcurrentTasks(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        $task1 = new ClosureTaskNode('task1', fn() => $this->createDummyTask());
        $task2 = new ClosureTaskNode('task2', fn() => $this->createDummyTask());
        $task3 = new ClosureTaskNode('task3', fn() => $this->createDummyTask());
        $task4 = new ClosureTaskNode('task4', fn() => $this->createDummyTask(), ['task1', 'task2']);
        $task5 = new ClosureTaskNode('task5', fn() => $this->createDummyTask(), ['task2', 'task3']);

        $root  = new SimpleTaskGroupNode('root', [$task1, $task2, $task3, $task4, $task5]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 3); // Allow 3 concurrent tasks

        // First three tasks should be available immediately
        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $firstId = $task->getId();
        $this->assertContains($firstId, ['task1', 'task2', 'task3']);

        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $secondId = $task->getId();
        $this->assertContains($secondId, ['task1', 'task2', 'task3']);
        $this->assertNotSame($firstId, $secondId);

        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $thirdId = $task->getId();
        $this->assertContains($thirdId, ['task1', 'task2', 'task3']);
        $this->assertNotSame($firstId, $thirdId);
        $this->assertNotSame($secondId, $thirdId);

        // Concurrency limit reached, should return true
        $this->assertTrue($queue->getNextTask());

        // Complete one task
        $queue->completeTask($firstId);

        // Should be able to get another task now
        $next = $queue->getNextTask();
        
        // If we get a boolean (true), it means we've hit the concurrency limit
        // This can happen if task4 and task5 both depend on tasks we haven't completed yet
        if ($next === true) {
            // Complete the other tasks first
            $queue->completeTask($secondId);
            $queue->completeTask($thirdId);
            
            // Now try to get the next task
            $next = $queue->getNextTask();
        }
        
        $this->assertInstanceOf(TaskLeafNode::class, $next);
        $fourthId = $next->getId();

        // Complete all remaining tasks
        // Only complete tasks that are actually running
        if (isset($secondId) && $secondId !== $firstId) { // Make sure we haven't already completed it
            $queue->completeTask($secondId);
        }
        if (isset($thirdId) && $thirdId !== $firstId) { // Make sure we haven't already completed it
            $queue->completeTask($thirdId);
        }
        $queue->completeTask($fourthId);

        // Get the final task
        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $queue->completeTask($task->getId());

        // No more tasks
        $this->assertFalse($queue->getNextTask());
    }

    public function testPriorityBasedOnDependents(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        // task1 has 3 dependents (directly or indirectly)
        // task2 has 2 dependents
        // task3 has 1 dependent
        // task4 has 0 dependents
        $task1 = new ClosureTaskNode('task1', fn() => $this->createDummyTask());
        $task2 = new ClosureTaskNode('task2', fn() => $this->createDummyTask(), ['task1']);
        $task3 = new ClosureTaskNode('task3', fn() => $this->createDummyTask(), ['task2']);
        $task4 = new ClosureTaskNode('task4', fn() => $this->createDummyTask(), ['task3']);
        $task5 = new ClosureTaskNode('task5', fn() => $this->createDummyTask(), ['task1']);
        $task6 = new ClosureTaskNode('task6', fn() => $this->createDummyTask(), ['task1']);

        $root  = new SimpleTaskGroupNode('root', [$task1, $task2, $task3, $task4, $task5, $task6]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 1);

        // task1 should be selected first due to having the most dependents
        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $this->assertSame('task1', $task->getId());
        $queue->completeTask('task1');

        // Now task2, task5, and task6 are ready, but task2 has more dependents
        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $this->assertSame('task2', $task->getId());
        $queue->completeTask('task2');

        // Complete remaining tasks in any order
        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $queue->completeTask($task->getId());

        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $queue->completeTask($task->getId());

        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $queue->completeTask($task->getId());

        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $queue->completeTask($task->getId());

        // No more tasks
        $this->assertFalse($queue->getNextTask());
    }

    public function testComplexDependencyChain(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        // Create a diamond dependency pattern with multiple paths
        $taskA = new ClosureTaskNode('taskA', fn() => $this->createDummyTask());
        $taskB1 = new ClosureTaskNode('taskB1', fn() => $this->createDummyTask(), ['taskA']);
        $taskB2 = new ClosureTaskNode('taskB2', fn() => $this->createDummyTask(), ['taskA']);
        $taskC = new ClosureTaskNode('taskC', fn() => $this->createDummyTask(), ['taskB1', 'taskB2']);
        $taskD1 = new ClosureTaskNode('taskD1', fn() => $this->createDummyTask(), ['taskC']);
        $taskD2 = new ClosureTaskNode('taskD2', fn() => $this->createDummyTask(), ['taskC']);
        $taskE = new ClosureTaskNode('taskE', fn() => $this->createDummyTask(), ['taskD1', 'taskD2']);

        $root  = new SimpleTaskGroupNode('root', [$taskA, $taskB1, $taskB2, $taskC, $taskD1, $taskD2, $taskE]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 2);

        // First task should be taskA
        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $this->assertSame('taskA', $task->getId());
        $queue->completeTask('taskA');

        // Next should be taskB1 and taskB2 (in any order)
        $task1 = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task1);
        $this->assertContains($task1->getId(), ['taskB1', 'taskB2']);
        
        $task2 = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task2);
        $this->assertContains($task2->getId(), ['taskB1', 'taskB2']);
        $this->assertNotSame($task1->getId(), $task2->getId());
        
        // Complete both B tasks
        $queue->completeTask($task1->getId());
        $queue->completeTask($task2->getId());

        // Next should be taskC
        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $this->assertSame('taskC', $task->getId());
        $queue->completeTask('taskC');

        // Next should be taskD1 and taskD2 (in any order)
        $task1 = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task1);
        $this->assertContains($task1->getId(), ['taskD1', 'taskD2']);
        
        $task2 = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task2);
        $this->assertContains($task2->getId(), ['taskD1', 'taskD2']);
        $this->assertNotSame($task1->getId(), $task2->getId());
        
        // Complete both D tasks
        $queue->completeTask($task1->getId());
        $queue->completeTask($task2->getId());

        // Next should be taskE
        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $this->assertSame('taskE', $task->getId());
        $queue->completeTask('taskE');

        // No more tasks
        $this->assertFalse($queue->getNextTask());
    }

    public function testFailTaskInComplexDependencyChain(): void
    {
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        // Create a complex dependency chain with multiple branches
        $taskA = new ClosureTaskNode('taskA', fn() => $this->createDummyTask());
        $taskB1 = new ClosureTaskNode('taskB1', fn() => $this->createDummyTask(), ['taskA']);
        $taskB2 = new ClosureTaskNode('taskB2', fn() => $this->createDummyTask(), ['taskA']);
        $taskC = new ClosureTaskNode('taskC', fn() => $this->createDummyTask(), ['taskB1', 'taskB2']);
        $taskD1 = new ClosureTaskNode('taskD1', fn() => $this->createDummyTask(), ['taskC']);
        $taskD2 = new ClosureTaskNode('taskD2', fn() => $this->createDummyTask(), ['taskC']);
        $taskE = new ClosureTaskNode('taskE', fn() => $this->createDummyTask(), ['taskD1', 'taskD2']);
        $taskF = new ClosureTaskNode('taskF', fn() => $this->createDummyTask(), ['taskA']); // Independent branch

        $root  = new SimpleTaskGroupNode('root', [$taskA, $taskB1, $taskB2, $taskC, $taskD1, $taskD2, $taskE, $taskF]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 2);

        // First task should be taskA
        $task = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task);
        $this->assertSame('taskA', $task->getId());
        $queue->completeTask('taskA');

        // Next should be taskB1, taskB2, and taskF (we'll get two due to concurrency limit)
        $task1 = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task1);
        $firstId = $task1->getId();
        $this->assertContains($firstId, ['taskB1', 'taskB2', 'taskF']);
        
        $task2 = $queue->getNextTask();
        $this->assertInstanceOf(TaskLeafNode::class, $task2);
        $secondId = $task2->getId();
        $this->assertContains($secondId, ['taskB1', 'taskB2', 'taskF']);
        $this->assertNotSame($firstId, $secondId);
        
        // Fail one of the B tasks if we got it
        if ($firstId === 'taskB1' || $firstId === 'taskB2') {
            $skipped = $queue->failTask($firstId);
            // Failing taskB1 or taskB2 should skip taskC, taskD1, taskD2, and taskE
            $this->assertCount(4, $skipped);
            $this->assertContains('taskC', $skipped);
            $this->assertContains('taskD1', $skipped);
            $this->assertContains('taskD2', $skipped);
            $this->assertContains('taskE', $skipped);
            
            // Complete the other task
            $queue->completeTask($secondId);
            
            // If the second task was taskF, we're done
            if ($secondId === 'taskF') {
                $this->assertFalse($queue->getNextTask());
            } else {
                // Otherwise, we should get taskF next
                $task = $queue->getNextTask();
                $this->assertInstanceOf(TaskLeafNode::class, $task);
                $this->assertSame('taskF', $task->getId());
                $queue->completeTask('taskF');
                $this->assertFalse($queue->getNextTask());
            }
        } else if ($secondId === 'taskB1' || $secondId === 'taskB2') {
            // First task must be taskF, complete it
            $queue->completeTask($firstId);
            
            // Fail the B task
            $skipped = $queue->failTask($secondId);
            // Failing taskB1 or taskB2 should skip taskC, taskD1, taskD2, and taskE
            $this->assertCount(4, $skipped);
            $this->assertContains('taskC', $skipped);
            $this->assertContains('taskD1', $skipped);
            $this->assertContains('taskD2', $skipped);
            $this->assertContains('taskE', $skipped);
            
            // Get the remaining B task
            $task = $queue->getNextTask();
            $this->assertInstanceOf(TaskLeafNode::class, $task);
            $this->assertContains($task->getId(), ['taskB1', 'taskB2']);
            $this->assertNotSame($secondId, $task->getId());
            $queue->completeTask($task->getId());
            
            // No more tasks
            $this->assertFalse($queue->getNextTask());
        } else {
            // Both tasks must be B tasks, fail one of them
            $skipped = $queue->failTask($firstId);
            // Failing taskB1 or taskB2 should skip taskC, taskD1, taskD2, and taskE
            $this->assertCount(4, $skipped);
            $this->assertContains('taskC', $skipped);
            $this->assertContains('taskD1', $skipped);
            $this->assertContains('taskD2', $skipped);
            $this->assertContains('taskE', $skipped);
            
            // Complete the other B task
            $queue->completeTask($secondId);
            
            // Get taskF
            $task = $queue->getNextTask();
            $this->assertInstanceOf(TaskLeafNode::class, $task);
            $this->assertSame('taskF', $task->getId());
            $queue->completeTask('taskF');
            
            // No more tasks
            $this->assertFalse($queue->getNextTask());
        }
    }

    public function testCompleteNonRunningTaskThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Cannot complete task 'task2' because it is not marked running.");
        
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        $task1 = new ClosureTaskNode('task1', fn() => $this->createDummyTask());
        $task2 = new ClosureTaskNode('task2', fn() => $this->createDummyTask(), ['task1']);

        $root  = new SimpleTaskGroupNode('root', [$task1, $task2]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 1);

        // Get and complete task1
        $task = $queue->getNextTask();
        $this->assertSame('task1', $task->getId());
        $queue->completeTask('task1');
        
        // Try to complete task2 without getting it first
        $queue->completeTask('task2');
    }

    public function testFailNonRunningTaskThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Cannot fail task 'task2' because it is not marked running.");
        
        $container = $this->createContainer();
        $factory   = new TaskTreeBuilderFactory($container);

        $task1 = new ClosureTaskNode('task1', fn() => $this->createDummyTask());
        $task2 = new ClosureTaskNode('task2', fn() => $this->createDummyTask(), ['task1']);

        $root  = new SimpleTaskGroupNode('root', [$task1, $task2]);
        $input = new ArrayInput([]);

        $taskList = new TaskList($factory, $root, $input);
        $queue    = new TaskQueue($taskList, $input, 1);

        // Get and complete task1
        $task = $queue->getNextTask();
        $this->assertSame('task1', $task->getId());
        $queue->completeTask('task1');
        
        // Try to fail task2 without getting it first
        $queue->failTask('task2');
    }
}

