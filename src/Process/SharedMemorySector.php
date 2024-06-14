<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\Sync\Mutex;
use Amp\Sync\PosixSemaphore;
use Amp\Sync\Semaphore;
use Amp\Sync\SemaphoreMutex;
use Amp\Sync\SharedMemoryParcel;
use ArrayObject;

class SharedMemorySector
{
    private Semaphore $semaphore;

    private Mutex $mutex;

    private SharedMemoryParcel $parcel;

    public readonly int $semaphoreKey;

    public readonly int $parcelKey;

    public function __construct(?int $semaphoreKey, ?int $parcelKey)
    {
        if ($semaphoreKey === null) {
            $this->semaphore = PosixSemaphore::create(1);
        } else {
            $this->semaphore = PosixSemaphore::use($semaphoreKey);
        }
        $this->semaphoreKey = $this->semaphore->getKey();
        $this->mutex = new SemaphoreMutex($this->semaphore);
        if ($parcelKey === null) {
            $this->parcel = SharedMemoryParcel::create($this->mutex, new ArrayObject());
            $this->update(static function (ArrayObject $storage) {
                $storage['createdAt'] = date(DATE_ATOM);
            });
        } else {
            $this->parcel = SharedMemoryParcel::use($this->mutex, $parcelKey);
        }
        $this->parcelKey = $this->parcel->getKey();
    }

    public function read(): ArrayObject
    {
        return $this->parcel->unwrap();
    }

    /**
     * Please avoid await in the updater function.
     * @param callable(ArrayObject $value): void $updater
     */
    public function update(callable $updater): void
    {
        $this->parcel->synchronized(static function (ArrayObject $storage) use ($updater) {
            $updater($storage);
            return $storage;
        });
    }
}
