<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

class CentralReadKeysRequest extends CentralReadRequest
{
    public function __construct(private readonly array $keys)
    {
    }

    public function &read(array &$cache): ?array
    {
        $result = [];
        foreach ($this->keys as $key) {
            if (isset($cache[$key])) {
                $result[$key] = &$cache[$key];
            }
        }

        return $result;
    }
}
