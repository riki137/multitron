<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;
use Multitron\Execution\Task;

final readonly class TaskNode
{
    /**
     * @param string $id
     * @param ?Closure(): Task $factory
     * @param TaskNode[] $children
     * @param array<TaskNode|string> $dependencies array of TaskNode IDs or TaskNode objects that this node depends on
     * @param string[] $tags Tags can be used for post-processing, for example, to run only tasks with a specific tag.
     * @param ?Closure(CompiledTaskNode[] $tasks): iterable<CompiledTaskNode> $postProcess This can be used for filtering, for example.
     */
    public function __construct(
        public string $id,
        public ?Closure $factory = null,
        public array $children = [],
        public array $dependencies = [],
        public array $tags = [],
        public ?Closure $postProcess = null,
    ) {
    }
}
