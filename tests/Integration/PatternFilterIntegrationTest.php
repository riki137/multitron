<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Tree\TaskTreeBuilder;
use Multitron\Tree\TaskTreeQueue;
use Multitron\Orchestrator\TaskList;
use Multitron\Execution\Task;
use Multitron\Comms\TaskCommunicator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class PatternFilterIntegrationTest extends TestCase
{
    private function createTaskList(): TaskList
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): object { return new $id(); }
            public function has(string $id): bool { return class_exists($id); }
        };
        $builder = new TaskTreeBuilder($container);

        $clean = $builder->task('cache-clear', fn() => new PatternFilterDummyTask());
        $unused = $builder->task('temp-clean', fn() => new PatternFilterDummyTask(), [$clean]);
        $warm = $builder->task('cache-warm', fn() => new PatternFilterDummyTask(), [$clean, $unused]);
        $cacheGroup = $builder->group('cache-group', [$clean, $warm]);

        $backup = $builder->task('db-backup', fn() => new PatternFilterDummyTask());
        $migrate = $builder->task('db-migrate', fn() => new PatternFilterDummyTask(), [$backup]);
        $dbGroup = $builder->group('db-group', [$backup, $migrate]);

        $miscGroup = $builder->group('misc-group', [$unused]);

        $report = $builder->task('final-report', fn() => new PatternFilterDummyTask(), [$warm, $migrate]);

        $root = $builder->patternFilter('root', 'db-*,cache-%,final-*', [
            $cacheGroup,
            $dbGroup,
            $miscGroup,
            $report,
        ]);

        return new TaskList($root);
    }

    public function testPatternFilterSelectsTasksAndFiltersDependencies(): void
    {
        $list = $this->createTaskList();
        $nodes = $list->toArray();

        $ids = array_keys($nodes);
        sort($ids);
        $this->assertSame([
            'cache-clear',
            'cache-warm',
            'db-backup',
            'db-migrate',
            'final-report',
            'temp-clean',
        ], $ids);

        $this->assertSame([], $nodes['cache-clear']->dependencies);
        $this->assertSame(['cache-clear'], $nodes['temp-clean']->dependencies);
        $this->assertSame(['cache-clear', 'temp-clean'], $nodes['cache-warm']->dependencies);
        $this->assertSame([], $nodes['db-backup']->dependencies);
        $this->assertSame(['db-backup'], $nodes['db-migrate']->dependencies);
        $this->assertSame(['cache-warm', 'db-migrate'], $nodes['final-report']->dependencies);

        $this->assertContains('cache-group', $nodes['cache-clear']->tags);
        $this->assertContains('db-group', $nodes['db-migrate']->tags);
        $this->assertContains('root', $nodes['final-report']->tags);
    }

    public function testExecutionOrderRespectsDependencies(): void
    {
        $list = $this->createTaskList();
        $queue = new TaskTreeQueue($list, 1);
        $iterator = $queue->getIterator();

        $order = [];
        while ($iterator->valid()) {
            $node = $iterator->current();
            if ($node !== null) {
                $order[] = $node->id;
                $queue->markCompleted($node->id);
            }
            $iterator->next();
        }

        $this->assertSame([
            'cache-clear',
            'db-backup',
            'temp-clean',
            'db-migrate',
            'cache-warm',
            'final-report',
        ], $order);
    }
}

final class PatternFilterDummyTask implements Task
{
    public function execute(TaskCommunicator $comm): void {}
}
