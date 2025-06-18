<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

final readonly class MasterCacheReadKeysRequest implements MasterCacheReadRequest
{
    /**
     * @param array<int|string, mixed> $keys ["key1", "key2"] to read top-level keys
     * [ "key1" => ["key1_1"], "key2" => ["key2_1", "key2_2"] ] to read nested keys
     * [ "key1", "key2" => ["key2_1", "key2_2"], "key3" => ["key3_1" => ["key3_1_1"]] ] feel free to mix depths
     */
    public function __construct(public array $keys)
    {
    }

    /**
     * Perform the actual read against the provided cache storage. Only values
     * matching the key specification are returned in the response message.
     *
     * @param array<int|string, mixed> $storage master cache contents
     */
    public function doRead(array &$storage): MasterCacheReadResponse
    {
        $data = $this->fetchKeys($storage, $this->keys);
        return new MasterCacheReadResponse($data);
    }

    /**
     * Recursively retrieve entries from the storage according to the key specification.
     *
     * @param array<int|string, mixed> $storage   data source to read from
     * @param array<int|string, mixed> $keysSpec  description of keys to fetch
     * @return array<string, mixed>               fetched data
     */
    private function fetchKeys(array &$storage, array $keysSpec): array
    {
        $result = [];

        foreach ($keysSpec as $key => $spec) {
            if (is_array($spec)) {
                if (isset($storage[$key]) && is_array($storage[$key])) {
                    $result[$key] = $this->fetchKeys($storage[$key], $spec);
                }
            } else {
                if (is_string($spec) || is_int($spec)) {
                    if (array_key_exists($spec, $storage)) {
                        $result[$spec] = $storage[$spec];
                    }
                }
            }
        }

        return $result;
    }
}
