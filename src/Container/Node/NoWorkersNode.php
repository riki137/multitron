<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

class NoWorkersNode extends TaskGroupNode
{
    public function getTasks(): iterable
    {
        foreach (parent::getTasks() as $node) {
            yield $node->setAsync();
        }
    }
}
