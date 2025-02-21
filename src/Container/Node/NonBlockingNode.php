<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Generator;
use InvalidArgumentException;
use Multitron\Process\TaskRunner;

/**
 * NonBlockingNode decorator that marks nodes for non-blocking processing.
 */
final class NonBlockingNode extends DecoratorNode
{
    /**
     * Processes and marks nodes as non-blocking.
     *
     * @return Generator<mixed> The processed nodes
     * @throws InvalidArgumentException If node processing fails
     */
    public function getProcessedNodes(): Generator
    {
        foreach (parent::getProcessedNodes() as $node) {
            $node->belongsTo(TaskRunner::NON_BLOCKING);
            yield $node;
        }
    }
}
