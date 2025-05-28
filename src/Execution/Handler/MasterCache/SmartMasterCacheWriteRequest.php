<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

/**
 * Builds and executes a batch of cache write and deep‐merge operations
 * against a PHP array “storage.”
 *
 * You queue any number of:
 *  - **write** operations: overwrite the value at a given path,
 *  - **merge** operations: deep‐merge an array into the existing array at that path.
 *
 * Then you call `doWrite()` once to apply **all** writes first (in the order you added them),
 * and **then** all merges (also in order).  Nested arrays are auto‐created as needed.
 *
 * @example
 * ```php
 * $storage = [];
 * $request = (new SmartMasterCacheWriteRequest())
 *     ->write(['user','name'], 'Alice')                      // overwrite user.name
 *     ->merge(['user','settings'], ['theme' => 'dark']);    // merge into user.settings
 *
 * $response = $request->doWrite($storage);
 * // Resulting $storage:
 * // [
 * //   'user' => [
 * //     'name'     => 'Alice',
 * //     'settings' => ['theme' => 'dark'],
 * //   ],
 * // ]
 * ```
 */
final class SmartMasterCacheWriteRequest implements MasterCacheWriteRequest
{
    /**
     * List of overwrite operations.
     *
     * Each entry is a two‐element array:
     *  0 => array<string> $path   (e.g. ['foo','bar','baz'])
     *  1 => mixed          $value  to set at that path
     *
     * @var array<int, array{0: string[], 1: mixed}>
     */
    public array $writeOps = [];

    /**
     * List of merge operations.
     *
     * Each entry is a two‐element array:
     *  0 => array<string>         $path
     *  1 => array<string, mixed>  $value  to deep‐merge into existing array
     *
     * @var array<int, array{0: string[], 1: array<string, mixed>}>
     */
    public array $mergeOps = [];

    public function __construct()
    {
    }

    /**
     * Queue an **overwrite** at the given path.
     *
     * Overwrites any existing value (even non‐array) at the final key.
     *
     * @param string[] $path  Sequence of keys to descend into the storage array.
     * @param mixed    $value Value to set at that location.
     * @return $this
     */
    public function write(array $path, mixed $value): self
    {
        $this->writeOps[] = [$path, $value];
        return $this;
    }

    /**
     * Queue a **deep‐merge** at the given path.
     *
     * If the existing value at the final key is an array, merges
     * (via array_merge) the queued array into it. Otherwise replaces.
     *
     * @param string[]             $path  Sequence of keys to target.
     * @param array<string,mixed>  $value Associative array to merge in.
     * @return $this
     */
    public function merge(array $path, array $value): self
    {
        $this->mergeOps[] = [$path, $value];
        return $this;
    }

    /**
     * Apply all queued writes and merges to the provided storage.
     *
     * 1. Applies **write** ops in queue order, creating nested arrays as needed.
     * 2. Applies **merge** ops in queue order, merging into existing arrays.
     *
     * @param array<string, mixed> $storage  Passed by reference; will be modified.
     * @return MasterCacheWriteResponse  (empty response object, currently no payload)
     */
    public function doWrite(array &$storage): MasterCacheWriteResponse
    {
        // Batch 0 = writeOps, batch 1 = mergeOps
        $opsLists = [$this->writeOps, $this->mergeOps];

        for ($batch = 0; $batch < 2; $batch++) {
            $ops  = $opsLists[$batch];
            $merge = ($batch === 1);

            foreach ($ops as [$segments, $value]) {
                $ref       = &$storage;
                $lastIndex = count($segments) - 1;

                // Descend (or create) nested arrays for all but the last segment.
                for ($i = 0; $i < $lastIndex; $i++) {
                    $key = $segments[$i];
                    if (!isset($ref[$key]) || !is_array($ref[$key])) {
                        $ref[$key] = [];
                    }
                    $ref = &$ref[$key];
                }

                $lastKey = $segments[$lastIndex];

                if ($merge) {
                    $existing = $ref[$lastKey] ?? [];
                    if (is_array($existing)) {
                        /** @var array<string,mixed> $existing */
                        /** @var array<string,mixed> $value */
                        $ref[$lastKey] = array_merge($existing, $value);
                    } else {
                        // Cannot merge non‐array: just overwrite
                        $ref[$lastKey] = $value;
                    }
                } else {
                    // Overwrite
                    $ref[$lastKey] = $value;
                }
            }
        }

        return new MasterCacheWriteResponse();
    }
}
