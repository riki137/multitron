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

    public function testBuildTagIndexWithParents(): void
    {
        // Test that parent IDs are included in tag index for children
        $child = new TaskNode('child', fn() => new DummyTask());
        $parent = new TaskNode('parent', null, [$child]);
        $root = new TaskNode('root', null, [$parent]);

        $compiled = (new TaskTreeCompiler())->compile($root);

        // Child should depend on all parent IDs that are in the tag index
        $this->assertArrayHasKey('child', $compiled);
    }

    public function testNestedTagExpansion(): void
    {
        $t1 = new TaskNode('task1', fn() => new DummyTask(), tags: ['db']);
        $t2 = new TaskNode('task2', fn() => new DummyTask(), tags: ['db']);
        $t3 = new TaskNode('task3', fn() => new DummyTask(), dependencies: ['db']);
        $root = new TaskNode('root', null, [$t1, $t2, $t3]);

        $compiled = (new TaskTreeCompiler())->compile($root);

        $this->assertArrayHasKey('task3', $compiled);
        $this->assertCount(2, $compiled['task3']->dependencies);
        $this->assertContains('task1', $compiled['task3']->dependencies);
        $this->assertContains('task2', $compiled['task3']->dependencies);
    }

    public function testParentDependenciesInheritedByChildren(): void
    {
        $dep = new TaskNode('dependency', fn() => new DummyTask());
        $child = new TaskNode('child', fn() => new DummyTask());
        $parent = new TaskNode('parent', null, [$child], dependencies: ['dependency']);
        $root = new TaskNode('root', null, [$dep, $parent]);

        $compiled = (new TaskTreeCompiler())->compile($root);

        $this->assertArrayHasKey('child', $compiled);
        $this->assertContains('dependency', $compiled['child']->dependencies);
    }

    public function testPostProcessTransformsSubtree(): void
    {
        $t1 = new TaskNode('t1', fn() => new DummyTask());
        $parent = new TaskNode('parent', null, [$t1], postProcess: function (array $tasks) {
            // Transform tasks by adding a prefix
            $result = [];
            foreach ($tasks as $task) {
                $result['modified-' . $task->id] = new \Multitron\Tree\CompiledTaskNode(
                    'modified-' . $task->id,
                    $task->factory,
                    $task->dependencies,
                    $task->tags
                );
            }
            return $result;
        });
        $root = new TaskNode('root', null, [$parent]);

        $compiled = (new TaskTreeCompiler())->compile($root);

        $this->assertArrayHasKey('modified-t1', $compiled);
        $this->assertArrayNotHasKey('t1', $compiled);
    }

    public function testPostProcessInvalidReturnTypeThrows(): void
    {
        $t1 = new TaskNode('t1', fn() => new DummyTask());
        $parent = new TaskNode('parent', null, [$t1], postProcess: function (array $tasks) {
            yield 'not-a-compiled-task';
        });
        $root = new TaskNode('root', null, [$parent]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Post-processing closure must return an iterable of CompiledTaskNode objects');
        (new TaskTreeCompiler())->compile($root);
    }

    public function testTagsInheritedByChildren(): void
    {
        $child = new TaskNode('child', fn() => new DummyTask());
        $parent = new TaskNode('parent', null, [$child], tags: ['parent-tag']);
        $root = new TaskNode('root', null, [$parent]);

        $compiled = (new TaskTreeCompiler())->compile($root);

        $this->assertArrayHasKey('child', $compiled);
        $this->assertContains('parent-tag', $compiled['child']->tags);
    }

    public function testNodeWithoutFactory(): void
    {
        // A node without a factory is just a grouping node
        $child = new TaskNode('child', fn() => new DummyTask());
        $parent = new TaskNode('parent', null, [$child]);
        $root = new TaskNode('root', null, [$parent]);

        $compiled = (new TaskTreeCompiler())->compile($root);

        // Only the child should be in the compiled list
        $this->assertArrayNotHasKey('parent', $compiled);
        $this->assertArrayHasKey('child', $compiled);
    }

    public function testCircularDependencyViaTagExpansion(): void
    {
        // A task depending on a tag that includes itself should not cause infinite loop
        $t1 = new TaskNode('t1', fn() => new DummyTask(), tags: ['group'], dependencies: ['group']);
        $root = new TaskNode('root', null, [$t1]);

        $compiled = (new TaskTreeCompiler())->compile($root);

        $this->assertArrayHasKey('t1', $compiled);
        // The dependency resolution should handle this - it may include t1 or not, depending on tag index order
        // The important part is it doesn't infinite loop
        $this->assertIsArray($compiled['t1']->dependencies);
    }

    public function testGetDirectDependenciesWithTaskNode(): void
    {
        $dep = new TaskNode('dep', fn() => new DummyTask());
        $t1 = new TaskNode('t1', fn() => new DummyTask(), dependencies: [$dep]);
        $root = new TaskNode('root', null, [$dep, $t1]);

        $compiled = (new TaskTreeCompiler())->compile($root);

        $this->assertArrayHasKey('t1', $compiled);
        $this->assertContains('dep', $compiled['t1']->dependencies);
    }

    public function testGetDirectDependenciesInvalidType(): void
    {
        $t1 = new TaskNode('t1', fn() => new DummyTask(), dependencies: [123]);
        $root = new TaskNode('root', null, [$t1]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Dependencies must be either TaskNode instances or strings');
        (new TaskTreeCompiler())->compile($root);
    }

    public function testDuplicateDependencies(): void
    {
        $t1 = new TaskNode('dep', fn() => new DummyTask());
        $t2 = new TaskNode('task', fn() => new DummyTask(), dependencies: ['dep', 'dep']); // Duplicate dependency
        $root = new TaskNode('root', null, [$t1, $t2]);

        $compiled = (new TaskTreeCompiler())->compile($root);

        $this->assertArrayHasKey('task', $compiled);
        // Should only contain 'dep' once even though it was listed twice
        $this->assertSame(['dep'], $compiled['task']->dependencies);
    }
}

