<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

class CentralWriteKeyRequest extends CentralWriteRequest
{
    public function __construct(private readonly string $key, private array &$data)
    {
    }

    public function write(array &$cache): void
    {
        $cache[$this->key] = &$this->data;
    }
}
