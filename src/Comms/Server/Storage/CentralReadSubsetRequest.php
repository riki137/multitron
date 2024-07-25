<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

class CentralReadSubsetRequest extends CentralReadRequest
{
    public function __construct(
        private readonly string $key,
        private readonly array &$subkeys
    ) {
    }

    public function &read(array &$cache): array
    {
        $result = [];
        if (!isset($cache[$this->key])) {
            return $result;
        }

        $sector = &$cache[$this->key];
        foreach ($this->subkeys as $subkey) {
            if (isset($sector[$subkey])) {
                $result[$subkey] = &$sector[$subkey];
            }
        }
        return $result;
    }
}
