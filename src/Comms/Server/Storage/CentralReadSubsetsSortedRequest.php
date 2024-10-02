<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

class CentralReadSubsetsSortedRequest extends CentralReadSubsetsRequest
{
    /**
     * @param array<string, string[]> $subsets
     */
    public function __construct(array $subsets)
    {
        foreach ($subsets as $key => &$subkeys) {
            sort($subkeys);
            $subsets[$key] = array_unique($subkeys);
        }
        ksort($subsets);
        parent::__construct($subsets);
    }
}
