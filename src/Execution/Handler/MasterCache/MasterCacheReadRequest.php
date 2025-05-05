<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use PhpStreamIpc\Message\Message;

final readonly class MasterCacheReadRequest implements Message
{
    /**
     * @param array $keys ["key1", "key2"] to read top-level keys
     * [ "key1" => ["key1_1"], "key2" => ["key2_1", "key2_2"] ] to read nested keys
     * [ "key1", "key2" => ["key2_1", "key2_2"], "key3" => ["key3_1" => ["key3_1_1"]] ] feel free to mix depths
     */
    public function __construct(public array $keys)
    {
    }
}
