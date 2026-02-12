<?php

declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Execution\Task;
use Multitron\Tree\TaskNode;
use PHPUnit\Framework\TestCase;

final class TaskNodeTest extends TestCase
{
    public function testConstructDefaultValues(): void
    {
        $node = new TaskNode('test-id');

        $this->assertSame('test-id', $node->id);
        $this->assertNull($node->factory);
        $this->assertSame([], $node->children);
        $this->assertSame([], $node->dependencies);
        $this->assertSame([], $node->tags);
        $this->assertNull($node->postProcess);
    }

    public function testConstructExplicitValues(): void
    {
        $factory = fn() => $this->createMock(Task::class);
        $children = [new TaskNode('child')];
        $dependencies = ['dep1'];
        $tags = ['tag1'];
        $postProcess = fn(array $tasks) => $tasks;

        $node = new TaskNode(
            id: 'test-id',
            factory: $factory,
            children: $children,
            dependencies: $dependencies,
            tags: $tags,
            postProcess: $postProcess
        );

        $this->assertSame('test-id', $node->id);
        $this->assertSame($factory, $node->factory);
        $this->assertSame($children, $node->children);
        $this->assertSame($dependencies, $node->dependencies);
        $this->assertSame($tags, $node->tags);
        $this->assertSame($postProcess, $node->postProcess);
    }

    public function testWith(): void
    {
        $node = new TaskNode('test-id');

        $factory = fn() => $this->createMock(Task::class);
        $children = [new TaskNode('child')];
        $dependencies = ['dep1'];
        $tags = ['tag1'];
        $postProcess = fn(array $tasks) => $tasks;

        $newNode = $node->with(
            factory: $factory,
            children: $children,
            dependencies: $dependencies,
            tags: $tags,
            postProcess: $postProcess
        );

        $this->assertNotSame($node, $newNode);
        $this->assertSame('test-id', $newNode->id);
        $this->assertSame($factory, $newNode->factory);
        $this->assertSame($children, $newNode->children);
        $this->assertSame($dependencies, $newNode->dependencies);
        $this->assertSame($tags, $newNode->tags);
        $this->assertSame($postProcess, $newNode->postProcess);

        // Test partial with
        $partialNode = $newNode->with(tags: ['new-tag']);
        $this->assertSame(['new-tag'], $partialNode->tags);
        $this->assertSame($factory, $partialNode->factory);
    }

    public function testWithAdded(): void
    {
        $node = new TaskNode(
            id: 'test-id',
            children: [new TaskNode('child1')],
            dependencies: ['dep1'],
            tags: ['tag1']
        );

        $child2 = new TaskNode('child2');
        $newNode = $node->withAdded(
            children: [$child2],
            dependencies: ['dep2'],
            tags: ['tag2']
        );

        $this->assertCount(2, $newNode->children);
        $this->assertSame($child2, $newNode->children[1]);
        $this->assertSame(['dep1', 'dep2'], $newNode->dependencies);
        $this->assertSame(['tag1', 'tag2'], $newNode->tags);
    }

    public function testWithout(): void
    {
        $factory = fn() => $this->createMock(Task::class);
        $child1 = new TaskNode('child1');
        $child2 = new TaskNode('child2');
        $dependencies = ['dep1', 'dep2'];
        $tags = ['tag1', 'tag2'];
        $postProcess = fn(array $tasks) => $tasks;

        $node = new TaskNode(
            id: 'test-id',
            factory: $factory,
            children: [$child1, $child2],
            dependencies: $dependencies,
            tags: $tags,
            postProcess: $postProcess
        );

        // Test clearing everything
        $cleared = $node->without(
            factory: true,
            children: true,
            dependencies: true,
            tags: true,
            postProcess: true
        );

        $this->assertNull($cleared->factory);
        $this->assertSame([], $cleared->children);
        $this->assertSame([], $cleared->dependencies);
        $this->assertSame([], $cleared->tags);
        $this->assertNull($cleared->postProcess);

        // Test removing specific items
        $filtered = $node->without(
            children: [$child1],
            dependencies: ['dep2'],
            tags: ['tag1']
        );

        $this->assertSame([$child2], $filtered->children);
        $this->assertSame(['dep1'], $filtered->dependencies);
        $this->assertSame(['tag2'], $filtered->tags);

        // Test false values (no change)
        $same = $node->without();
        $this->assertSame($factory, $same->factory);
        $this->assertSame([$child1, $child2], $same->children);
        $this->assertSame($dependencies, $same->dependencies);
        $this->assertSame($tags, $same->tags);
        $this->assertSame($postProcess, $same->postProcess);
    }

    public function testWithoutWithMixedDependencies(): void
    {
        $child1 = new TaskNode('child1');
        $node = new TaskNode(
            id: 'test',
            dependencies: [$child1, 'dep1']
        );

        $filtered = $node->without(dependencies: [$child1]);
        $this->assertSame(['dep1'], $filtered->dependencies);

        $filtered2 = $node->without(dependencies: ['dep1']);
        $this->assertSame([$child1], $filtered2->dependencies);
    }

    public function testIsLeaf(): void
    {
        $node = new TaskNode('leaf');
        $this->assertTrue($node->isLeaf());

        $parentNode = new TaskNode('parent', children: [$node]);
        $this->assertFalse($parentNode->isLeaf());
    }
}
