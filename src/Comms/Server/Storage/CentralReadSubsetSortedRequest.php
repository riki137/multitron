<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

class CentralReadSubsetSortedRequest extends CentralReadSubsetRequest
{
    /**
     * @param string[] $subkeys
     */
    public function __construct(string $key, array &$subkeys)
    {
        sort($subkeys);
        $subkeys = array_unique($subkeys);
        parent::__construct($key, $subkeys);
    }
}
