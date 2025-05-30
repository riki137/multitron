<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use LogicException;
use function count;
use function is_array;

/**
 * Batches and applies write and merge operations to a nested array (“master cache”) in the correct
 * hierarchical order, based on dot-notation paths.
 *
 * You can queue writes and deep-merges using human-friendly “a.b.c” paths, chain multiple operations,
 * and then apply them all at once to your storage array.  Writes at shallower depths are guaranteed
 * to be applied before deeper ones, so merges and overwrites happen predictably.
 *
 * Example:
 * ```php
 * $storage = [];
 * $request = new SmartMasterCacheWriteRequest();
 *
 * $request
 *     ->write('user.profile.name', 'Alice')
 *     ->merge('user.settings', [
 *         'theme'         => 'dark',
 *         'notifications' => true,
 *     ])
 *     ->write('user.profile.age', 30)
 *     ->doWrite($storage);
 *
 * // $storage now contains:
 * // [
 * //   'user' => [
 * //     'profile' => [
 * //       'name' => 'Alice',
 * //       'age'  => 30,
 * //     ],
 * //     'settings' => [
 * //       'theme'         => 'dark',
 * //       'notifications' => true,
 * //     ],
 * //   ],
 * // ]
 * ```
 */
final class MasterCacheWriteKeysRequest implements MasterCacheWriteRequest
{
    /** @var array<int,array>  Write buckets keyed by path-segment depth */
    private array $writeBuckets = [];

    /**
     * Initialize an empty batch of write/merge operations.
     */
    public function __construct()
    {
    }

    /**
     * Queue a write operation for a given dot-notation path.
     *
     * @param string $dotPath Dot-notation path (e.g. 'a.b.c') where the value will be set.
     * @param mixed  $value   The value to set at that path.
     * @return $this          Fluent interface for chaining.
     */
    public function write(string $dotPath, mixed $value): self
    {
        $path = explode('.', $dotPath);
        return $this->writeFast(count($path), $path, $value);
    }

    /**
     * Use this to achieve higher performance when writing a lot of values.
     *
     * @param int    $level Number of segments in $path.
     * @param string[] $path Array of path segments.
     * @param mixed  $value Value to write at the target location.
     * @return $this        Fluent interface.
     */
    public function writeFast(int $level, array $path, mixed $value): self
    {
        if ($level < 1) {
            throw new LogicException('Cannot write at depth less than 1');
        }
        $ref = &$this->writeBuckets[$level];

        // Build nested map down to the target depth
        foreach ($path as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        // At the final level, overwrite with the actual value
        $ref = $value;
        return $this;
    }

    /**
     * Queue a deep-merge operation: each key/value in the array becomes its own write
     * at path + [key], preserving the existing nested structure.
     *
     * @param string $dotPath Dot-notation base path (e.g. 'a.b').
     * @param array  $value   Associative array to merge under that path.
     * @return $this          Fluent interface for chaining.
     */
    public function merge(string $dotPath, array $value): self
    {
        $path = explode('.', $dotPath);
        return $this->mergeFast(count($path), $path, $value);
    }

    /**
     * Use this to achieve higher performance when merging a lot of values.
     *
     * @param int      $level Depth of the base path.
     * @param string[] $path  Array of base path segments.
     * @param array    $new   Associative array whose entries will be written one level deeper.
     * @return $this          Fluent interface.
     */
    public function mergeFast(int $level, array $path, array $new): self
    {
        if ($level < 1) {
            throw new LogicException('Cannot merge at depth less than 1');
        }
        // Write buckets at one level deeper than the base
        $ref = &$this->writeBuckets[$level + 1];

        // Build nested map down to the merge point
        foreach ($path as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        // Each new key becomes its own write
        foreach ($new as $segment => $value) {
            $ref[$segment] = $value;
        }

        return $this;
    }

    /**
     * Apply all queued writes and merges to the given storage array.
     *
     * Operations are executed in ascending order of their original dot-path depth,
     * ensuring that parent nodes exist before children are written.
     *
     * @param array<string, mixed> &$storage Reference to the target nested array.
     * @return MasterCacheWriteResponse A response object (always success in this implementation).
     */
    public function doWrite(array &$storage): MasterCacheWriteResponse
    {
        // Sort buckets by depth (shallow first)
        ksort($this->writeBuckets);
        foreach ($this->writeBuckets as $depth => $ops) {
            $this->applyWrites($storage, $ops, $depth);
        }

        return new MasterCacheWriteResponse();
    }

    /**
     * @internal
     *
     * Recursively apply a set of write operations to a node at a given depth.
     *
     * @param array<mixed> &$node  Target subtree to modify.
     * @param array        $ops    Nested map of operations for this depth.
     * @param int          $depth  Levels remaining until leaf writes.
     */
    private function applyWrites(array &$node, array $ops, int $depth): void
    {
        if ($depth === 1) {
            foreach ($ops as $key => $value) {
                $node[$key] = $value;
            }
        } else {
            foreach ($ops as $key => $subOps) {
                if (!is_array($node[$key] ?? null)) {
                    $node[$key] = [];
                }
                $this->applyWrites($node[$key], $subOps, $depth - 1);
            }
        }
    }
}
