<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use StreamIpc\Message\Message;

interface MasterCacheWriteRequest extends Message
{
    /**
     * @param array<string, mixed> $storage
     */
    public function doWrite(array &$storage): MasterCacheWriteResponse;
}
