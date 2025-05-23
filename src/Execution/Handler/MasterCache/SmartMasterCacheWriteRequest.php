<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

final class SmartMasterCacheWriteRequest implements MasterCacheWriteRequest
{
    /** @var array<array{string[], mixed}> */
    public array $writeOps = [];

    /** @var array<array{string[], array<string, mixed>}> */
    public array $mergeOps = [];

    public function __construct()
    {
    }

    /**
     * Queue an overwrite at $path.
     *
     * @param string[] $path
     */
    public function write(array $path, mixed $value): self
    {
        $this->writeOps[] = [$path, $value];
        return $this;
    }

    /**
     * Queue a deep‐merge at $path (arrays only).
     *
     * @param string[] $path
     * @param array<string, mixed> $value
     */
    public function merge(array $path, array $value): self
    {
        $this->mergeOps[] = [$path, $value];
        return $this;
    }

    /**
     * @param array<string, mixed> $storage
     */
    public function doWrite(array &$storage): MasterCacheWriteResponse
    {
        // batch 0 = write, batch 1 = merge
        $opsLists = [$this->writeOps, $this->mergeOps];

        for ($batch = 0; $batch < 2; $batch++) {
            $ops = $opsLists[$batch];
            $merge = $batch === 1;
            $nOps = count($ops);

            for ($i = 0; $i < $nOps; $i++) {
                [$segments, $value] = $ops[$i];
                $ref = &$storage;
                $lastIndex = count($segments) - 1;

                // descend into nested arrays
                for ($j = 0; $j < $lastIndex; $j++) {
                    $key = $segments[$j];
                    if (!isset($ref[$key]) || !is_array($ref[$key])) {
                        $ref[$key] = [];
                    }
                    $ref = &$ref[$key];
                }

                $lastKey = $segments[$lastIndex];

                if ($merge) {
                    $existing = $ref[$lastKey] ?? [];
                    if (is_array($existing)) {
                        /** @var array<string, mixed> $existing */
                        /** @var array<string, mixed> $value */
                        $ref[$lastKey] = array_merge($existing, $value);
                    } else {
                        $ref[$lastKey] = $value;
                    }
                } else {
                    $ref[$lastKey] = $value;
                }
            }
        }

        return new MasterCacheWriteResponse();
    }
}
