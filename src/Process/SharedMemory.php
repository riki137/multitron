<?php
declare(strict_types=1);

namespace Multitron\Process;

use ArrayObject;
use RuntimeException;

class SharedMemory
{
    private SharedMemorySector $keys;

    private ArrayObject $keyCache;

    private array $sectorCache = [];

    public readonly int $semaphoreKey;

    public readonly int $parcelKey;

    public function __construct(?int $semaphoreKey, ?int $mutexKey)
    {
        $this->keyCache = new ArrayObject();
        $this->keys = new SharedMemorySector($semaphoreKey, $mutexKey);
        $this->semaphoreKey = $this->keys->semaphoreKey;
        $this->parcelKey = $this->keys->parcelKey;
    }

    private function obtainSector(string $key): SharedMemorySector
    {
        if (isset($this->keyCache[$key])) {
            return $this->sectorCache[$key] ??= new SharedMemorySector(
                $this->keyCache[$key]['semaphoreKey'],
                $this->keyCache[$key]['parcelKey']
            );
        }

        $this->keys->update(function (ArrayObject $keys) use ($key) {
            if (isset($keys[$key]['semaphoreKey'], $keys[$key]['parcelKey'])) {
                $this->sectorCache[$key] = new SharedMemorySector($keys[$key]['semaphoreKey'], $keys[$key]['parcelKey']);
                return;
            }
            $sector = $this->sectorCache[$key] = new SharedMemorySector(null, null);
            $keys[$key] = [
                'semaphoreKey' => $sector->semaphoreKey,
                'parcelKey' => $sector->parcelKey,
            ];
            $this->keyCache = $keys;
        });
        if (!$this->sectorCache[$key] instanceof SharedMemorySector) {
            throw new RuntimeException('Failed to obtain sector');
        }
        return $this->sectorCache[$key];
    }

    public function get(string $key): ArrayObject
    {
        return $this->obtainSector($key)->read();
    }

    /**
     * Please avoid await in the updater function unless absolutely necessary.
     * @param callable(ArrayObject $value): void $updater
     */
    public function update(string $key, callable $updater): void
    {
        $this->obtainSector($key)->update($updater);
    }
}
