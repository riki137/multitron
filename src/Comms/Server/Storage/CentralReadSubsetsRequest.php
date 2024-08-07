<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

class CentralReadSubsetsRequest extends CentralReadRequest
{
    public function __construct(private array $subsets = [])
    {
    }

    /**
     * @param string $key
     * @param string[] $subKeys
     * @return $this
     */
    public function with(string $key, array $subKeys): self
    {
        foreach ($subKeys as $subKey) {
            $this->subsets[$key][] = $subKey;
        }
        return $this;
    }

    public function &read(array &$cache): array
    {
        $result = [];
        foreach ($this->subsets as $key => $subKeys) {
            if (!isset($cache[$key])) {
                continue;
            }

            $sector = &$cache[$key];
            foreach ($subKeys as $subKey) {
                if (isset($sector[$subKey])) {
                    $result[$key][$subKey] = $sector[$subKey];
                }
            }
        }
        return $result;
    }
}
