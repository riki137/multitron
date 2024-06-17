<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\DeferredFuture;
use Amp\Future;
use Multitron\Container\Node\TaskNode;
use Multitron\Container\Node\TaskTreeProcessor;
use RuntimeException;
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

    private array $dependencyCache = [];

    public function __construct(private readonly int $concurrencyLimit, private readonly TaskTreeProcessor $treeProcessor)
    {
    }

    public function getNodes(): iterable
    {
        return $this->treeProcessor->getNodes();
    }

    public function fetchAll(): iterable
    {
        $queue = $this->treeProcessor->getNodes();
        do {
            foreach ($queue as $id => $node) {
                if (count($this->futures) >= $this->concurrencyLimit) {
                    break;
                }
                $deps = array_diff($this->getDependencies($node), $this->finished);
                if (empty($deps)) {
                    unset($queue[$id]);
                    $this->deferredFutures[$id] = new DeferredFuture();
                    $this->futures[$id] = $this->deferredFutures[$id]->getFuture();
                    yield $id => $node;
                }
            }
            if (empty($this->futures)) {
                throw new RuntimeException('Circular dependency detected with the following tasks left: ' . implode(
                    ', ',
                    array_keys($queue)
                ));
            }
            try {
                $this->markFinished(awaitFirst($this->futures));
            } catch (Throwable $e) {
                Debugger::log($e, 'queue-wait');
            }
        } while ($queue !== []);
    }

    private function getDependencies(TaskNode $node): array
    {
        $nodeId = $node->getId();
        if (isset($this->dependencyCache[$nodeId])) {
            return $this->dependencyCache[$nodeId];
        }

        $deps = [];
        foreach ($node->getDependencies() as $dep) {
            $this->unpackIfGroup($dep, $deps);
        }

        return $this->dependencyCache[$nodeId] = array_unique($deps);
    }

    private function unpackIfGroup(string $id, array &$deps): void
    {
        if ($this->treeProcessor->isGroup($id)) {
            foreach ($this->treeProcessor->getIdsInGroup($id) as $groupId) {
                $this->unpackIfGroup($groupId, $deps);
            }
        } else {
            $deps[] = $id;
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
