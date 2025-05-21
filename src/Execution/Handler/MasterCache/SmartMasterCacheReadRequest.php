<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

final readonly class SmartMasterCacheReadRequest implements MasterCacheReadRequest
{
    /**
     * @param array $keys ["key1", "key2"] to read top-level keys
     * [ "key1" => ["key1_1"], "key2" => ["key2_1", "key2_2"] ] to read nested keys
     * [ "key1", "key2" => ["key2_1", "key2_2"], "key3" => ["key3_1" => ["key3_1_1"]] ] feel free to mix depths
     */
    public function __construct(public array &$keys)
    {
    }

    /**
     * Handles a request to read data from the cache.
     */
    public function doRead(array &$storage): MasterCacheReadResponse
    {
        $data = $this->fetchKeys($storage, $this->keys);
        return new MasterCacheReadResponse($data);
    }

    /**
     * Recursively retrieves entries from $source according to $keysSpec.
     */
    private function fetchKeys(array &$source, array $keysSpec): array
    {
        $result = [];

        foreach ($keysSpec as $outerKey => $innerSpec) {
            // simple top-level key
            if (is_string($innerSpec)) {
                if (isset($source[$innerSpec])) {
                    $result[$innerSpec] = $source[$innerSpec];
                }
                // nested structure
            } elseif (is_array($innerSpec) && isset($source[$outerKey]) && is_array($source[$outerKey])) {
                $nested = $this->fetchKeys($source[$outerKey], $innerSpec);
                if ($nested !== []) {
                    $result[$outerKey] = $nested;
                }
            }
        }

        return $result;
    }
}
