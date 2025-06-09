<?php
declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use LogicException;

/**
 * Request that writes key-value chunks into the master-cache with
 * depth-limited precedence.  Uses a simple array instead of
 * {@see \SplPriorityQueue} so the object can be serialised.
 */
final class MasterCacheWriteKeysRequest implements MasterCacheWriteRequest
{
    /**
     * @var array<int,list<array>>
     *            depth => [ chunk, chunk, … ]
     */
    private array $layers = [];

    /** Fast path – the shallowest depth we have seen (∞ if none) */
    private int $minDepth = PHP_INT_MAX;

    public function write(array $data, ?int $depth = null): self
    {
        $depth ??= $this->detectDepthIterative($data);

        if ($depth < 1) {
            throw new LogicException('Write precedence level must be >= 1');
        }

        // bucket by depth; keep FIFO order inside each bucket
        $this->layers[$depth][] = $data;
        $this->minDepth = min($this->minDepth, $depth);

        return $this;
    }

    public function doWrite(array &$storage): MasterCacheWriteResponse
    {
        if ($this->minDepth === PHP_INT_MAX) {
            // nothing queued
            return new MasterCacheWriteResponse();
        }

        /**
         * We almost always have ≤ a dozen depth levels, so the
         * O(D log D) ksort() is negligible and avoids a custom heap.
         */
        ksort($this->layers, SORT_NUMERIC);

        foreach ($this->layers as $depth => $chunks) {
            foreach ($chunks as $chunk) {
                $this->mergeInPlace($storage, $chunk, $depth);
            }
        }

        return new MasterCacheWriteResponse();
    }

    /* ---------- internals (unchanged) ---------- */

    private function mergeInPlace(array &$base, array $new, int $limit): void
    {
        if ($limit <= 1) {
            foreach ($new as $k => $v) {
                $base[$k] = $v;
            }
            return;
        }

        $stack = [ [&$base, $new, $limit] ];
        while ($stack) {
            [ $target, $src, $lvl ] = array_pop($stack);
            foreach ($src as $k => $v) {
                if ($lvl > 1 && isset($target[$k]) && is_array($target[$k]) && is_array($v)) {
                    $stack[] = [&$target[$k], $v, $lvl - 1];
                } else {
                    $target[$k] = $v;
                }
            }
        }
    }

    private function detectDepthIterative(array $data): int
    {
        $maxDepth = 1;
        $stack = [[1, $data]];

        while ($stack) {
            [ $depth, $node ] = array_pop($stack);
            $maxDepth = max($maxDepth, $depth);
            foreach ($node as $child) {
                if (is_array($child)) {
                    $stack[] = [$depth + 1, $child];
                }
            }
        }

        return $maxDepth;
    }
}
