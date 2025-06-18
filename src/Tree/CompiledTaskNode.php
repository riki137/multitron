<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;

/**
 * Represents a compiled task node in the task tree.
 * This class is used to store the final structure of a task after it has been processed.
 * That means the dependencies contain dependencies of all the parents and tags contain all the parents ids (groups) and parent's tags recursively.
 */
final readonly class CompiledTaskNode
{
    public function __construct(
        public string $id,
        public Closure $factory,
        /** @var string[] */
        public array $dependencies = [],
        /** @var string[] */
        public array $tags = [],
    ) {
    }
}
