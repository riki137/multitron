<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\DeferredFuture;
use Amp\Future;
use Generator;
use Multitron\Container\Node\TaskNodeLeaf;
use Multitron\Container\Node\TaskTreeProcessor;
use Multitron\Error\ErrorHandler;
use RuntimeException;
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

    public function __construct(
        private readonly int $concurrencyLimit,
        private readonly TaskTreeProcessor $treeProcessor,
        private readonly ErrorHandler $errorHandler
    ) {
    }

    /**
     * @return iterable<string, TaskNodeLeaf>
     */
    public function fetchAll(): iterable
    {
        $queue = $this->treeProcessor->getLeaves();
        do {
            $chunk = [];
            $this->ksortByPriority($queue);
            foreach ($this->throttleConcurrent($queue) as $id => $node) {
                $deps = $this->treeProcessor->getDependentIds($node);
                $deps = array_diff($deps, $this->finished);
                if (count($deps) !== count(array_unique($deps))) {
                    throw new RuntimeException($id . ' Deps are not unique: ' . implode(', ', $deps));
                }
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
                throw $e;
            }
            yield from $chunk;
        } while ($queue !== []);
    }

    private function throttleConcurrent(iterable $items): Generator
    {
        if ($this->concurrencyLimit === 0) {
            yield from $items;
            return;
        }
        foreach ($items as $id => $item) {
            $futures = array_filter($this->futures, fn(Future $future) => !$future->isComplete());
            $currentlyRunning = count($futures);
            if ($currentlyRunning >= $this->concurrencyLimit) {
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

    /**
     * This function sorts the task nodes by priority, with the highest priority first.
     *
     * @param array<string, TaskNodeLeaf> $nodes array of task nodes, keyed by task id
     * @return void
     */
    public function ksortByPriority(array &$nodes): void
    {
        uksort($nodes, function (string $a, string $b) {
            $aDeps = $this->treeProcessor->getDependentIds($a);
            $bDeps = $this->treeProcessor->getDependentIds($b);
            $aUnfinished = array_diff($aDeps, $this->finished);
            $bUnfinished = array_diff($bDeps, $this->finished);
            return count($aUnfinished) <=> count($bUnfinished);
        });
    }
}
