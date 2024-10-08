<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

final class CentralReadKeyRequest extends CentralReadRequest
{
    public function __construct(private readonly string $key)
    {
    }

    public function &read(array &$cache): array
    {
        if (isset($cache[$this->key])) {
            return $cache[$this->key];
        }

        $result = [];
        return $result;
    }
}
