<?php
declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use LogicException;

/**
 * Request that writes key-value chunks into the master-cache with
 * depth-limited precedence.
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

    /**
     * @param array<int|string, mixed> $data
     */
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

    /**
     * @param array<int|string, mixed> $storage
     */
    public function doWrite(array &$storage): void
    {
        if ($this->minDepth === PHP_INT_MAX) {
            // nothing queued
            return;
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

        return;
    }

    /**
     * @param array<int|string, mixed> $base
     * @param array<int|string, mixed> $new
     */
    private function mergeInPlace(array &$base, array $new, int $limit): void
    {
        if ($limit <= 1) {
            foreach ($new as $k => $v) {
                $base[$k] = $v;
            }
            return;
        }

        foreach ($new as $k => $v) {
            if (isset($base[$k]) && is_array($base[$k]) && is_array($v)) {
                $this->mergeInPlace($base[$k], $v, $limit - 1);
            } else {
                $base[$k] = $v;
            }
        }
    }

    /**
     * @param array<int|string, mixed> $data
     */
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
