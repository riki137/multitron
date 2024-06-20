<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\DeferredFuture;
use Amp\Future;
use Multitron\Container\Node\TaskNode;
use Multitron\Container\Node\TaskTreeProcessor;
use Throwable;
use Tracy\Debugger;
use function Amp\Future\awaitFirst;

class TaskQueue
{
    /** @var array<string, DeferredFuture> */
    private array $deferredFutures = [];

    /** @var array<string, Future> */
    private array $futures = [];

    /** @var string[] */
    private array $finished = [];

    public function __construct(private readonly int $concurrencyLimit, private readonly TaskTreeProcessor $treeProcessor)
    {
    }

    /**
     * @return iterable<string, TaskNode>
     */
    public function fetchAll(): iterable
    {
        $queue = $this->treeProcessor->getNodes();
        do {
            $chunk = [];
            foreach ($queue as $id => $node) {
                if (count($this->futures) > $this->concurrencyLimit) {
                    break;
                }
                $deps = array_diff($this->treeProcessor->getDependencies($node), $this->finished);
                if (empty($deps)) {
                    unset($queue[$id]);
                    $this->deferredFutures[$id] = new DeferredFuture();
                    $this->futures[$id] = $this->deferredFutures[$id]->getFuture();
                    $chunk[$id] = $node;
                }
            }
            try {
                $this->markFinished(awaitFirst($this->futures));
            } catch (Throwable $e) {
                Debugger::log($e, 'queue-wait');
            }
            $this->treeProcessor->ksort($chunk);
            yield from $chunk;
        } while ($queue !== []);
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
