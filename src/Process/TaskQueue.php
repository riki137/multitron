<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\DeferredFuture;
use Amp\Future;
use Generator;
use Multitron\Container\Node\TaskLeafNode;
use Multitron\Container\Node\TaskTreeProcessor;
use Multitron\Error\ErrorHandler;
use Throwable;
use function Amp\async;
use function Amp\delay;
use function Amp\Future\awaitFirst;

class TaskQueue
{
    /** @var DeferredFuture[] */
    private array $deferredFutures = [];

    /** @var Future[] */
    private array $futures = [];

    /** @var string[] */
    private array $finished = [];

    public function __construct(private readonly int $concurrencyLimit, private readonly TaskTreeProcessor $treeProcessor, private readonly ErrorHandler $errorHandler)
    {
    }

    /**
     * @return iterable<string, TaskLeafNode>
     */
    public function fetchAll(): iterable
    {
        $queue = $this->treeProcessor->getNodes();
        do {
            $chunk = [];
            $this->treeProcessor->ksortByPriority($queue, $this->finished);
            foreach ($this->throttleConcurrent($queue) as $id => $node) {
                $deps = $this->treeProcessor->getDependencies($node);
                $deps = array_diff($deps, $this->finished);
                if (empty($deps)) {
                    unset($queue[$id]);
                    $this->deferredFutures[$id] = new DeferredFuture();
                    $this->futures[$id] = $this->deferredFutures[$id]->getFuture();
                    $chunk[$id] = $node;
                }
            }
            try {
                awaitFirst([async(fn() => delay(0.2)), ...$this->futures]);
                $this->futures = array_filter($this->futures, fn(Future $future) => !$future->isComplete());
            } catch (Throwable $e) {
                $this->errorHandler->internalError($e);
            }
            yield from $chunk;
        } while ($queue !== []);
    }

    private function throttleConcurrent(iterable $items): Generator
    {
        foreach ($items as $id => $item) {
            if ($this->concurrencyLimit === 0) {
                yield $id => $item;
                continue;
            }
            $futures = array_filter($this->futures, fn(Future $future) => !$future->isComplete());
            $count = count($futures);
            if ($count >= $this->concurrencyLimit) {
                return;
            }
            yield $id => $item;
        }
    }

    public function markFinished(string $id): void
    {
        if (!isset($this->futures[$id])) {
            return;
        }
        $this->finished[] = $id;
        $this->deferredFutures[$id]->complete($id);
        unset($this->deferredFutures[$id], $this->futures[$id]);
    }
}
