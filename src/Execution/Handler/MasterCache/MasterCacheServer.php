<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

/**
 * Represents an in-memory cache store accessible by workers.
 */
class MasterCacheServer
{
    /** @var array<string, mixed> */
    private array $storage = [];

    public function __construct()
    {
    }

    /**
     * Handles a request to read data from the cache.
     */
    public function handleRead(MasterCacheReadRequest $request): MasterCacheReadResponse
    {
        $data = $this->fetchKeys($this->storage, $request->keys);
        return new MasterCacheReadResponse($data);
    }

    /**
     * Recursively retrieves entries from $source according to $keysSpec.
     *
     * @param array<string, mixed> $source
     * @param array<mixed> $keysSpec
     * @return array<string, mixed>
     */
    private function fetchKeys(array $source, array $keysSpec): array
    {
        $result = [];

        foreach ($keysSpec as $outerKey => $innerSpec) {
            // simple top-level key (numeric index in keysSpec => string key name)
            if (is_string($innerSpec)) {
                if (isset($source[$innerSpec])) {
                    $result[$innerSpec] = $source[$innerSpec];
                }

                // nested structure (string key => array of deeper keys)
            } elseif (is_array($innerSpec) && isset($source[$outerKey]) && is_array($source[$outerKey])) {
                $nested = $this->fetchKeys($source[$outerKey], $innerSpec);
                if ($nested !== []) {
                    $result[$outerKey] = $nested;
                }
            }
            // anything else: ignored
        }

        return $result;
    }

    public function handleWrite(MasterCacheWriteRequest $request): MasterCacheWriteResponse
    {
        foreach ($request->ops as [$op, $segments, $value]) {
            // drill down to leaf parent
            $ref = &$storage;
            $last = array_pop($segments);
            foreach ($segments as $seg) {
                if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                    $ref[$seg] = [];
                }
                $ref = &$ref[$seg];
            }

            if ($op === MasterCacheWriteRequest::OP_WRITE) {
                $ref[$last] = $value;
            } else {
                // merge
                $existing = $ref[$last] ?? [];
                if (!is_array($existing)) {
                    // overwrite non-array with array
                    $ref[$last] = $value;
                } else {
                    $ref[$last] = array_merge($existing, $value);
                }
            }
        }

        return new MasterCacheWriteResponse();
    }

    /**
     * Clears entire cache.
     */
    public function clear(): void
    {
        $this->storage = [];
    }
}
