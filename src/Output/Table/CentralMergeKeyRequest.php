<?php

declare(strict_types=1);

namespace Multitron\Output\Table;

use Multitron\Comms\Server\Storage\CentralWriteRequest;

class CentralMergeKeyRequest extends CentralWriteRequest
{
    /**
     * @param int<1,max> $level
     */
    public function __construct(private readonly string $key, private array &$data, private readonly int $level = 1)
    {
    }

    public function write(array &$cache): void
    {
        $cache[$this->key] ??= [];

        $this->merge($cache[$this->key], $this->data, $this->level);
    }

    private function merge(array &$cache, array &$data, int $level = 1): void
    {
        foreach ($data as $key => &$value) {
            if ($level > 1) {
                $this->merge($cache[$key], $value, $level - 1);
            } else {
                $cache[$key] = &$value;
            }
        }
    }
}
