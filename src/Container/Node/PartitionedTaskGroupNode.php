<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Closure;

class PartitionedTaskGroupNode extends TaskGroupNode
{
    public function __construct(string $id, Closure $parentFactory, int $chunks)
    {
        parent::__construct($id, function () use ($id, $parentFactory, $chunks) {
            for ($i = 1; $i <= $chunks; $i++) {
                yield new PartitionedTaskLeafNode($id . ": $i/$chunks", $parentFactory, $i - 1, $chunks);
            }
        });
    }
}
