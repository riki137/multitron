<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

class NonBlockingNode extends TaskGroupNode
{
    public function getTasks(): iterable
    {
        foreach (parent::getTasks() as $node) {
            yield $node->setNonBlocking();
        }
    }
}
