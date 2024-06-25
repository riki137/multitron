<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

class CentralMergeKeyRequest extends CentralWriteRequest
{
    public function __construct(private readonly string $key, private array &$data)
    {
    }

    public function write(array &$cache): void
    {
        $cache[$this->key] ??= [];
        $sector = &$cache[$this->key];

        foreach ($this->data as $key => &$value) {
            $sector[$key] = &$value;
        }
    }
}
