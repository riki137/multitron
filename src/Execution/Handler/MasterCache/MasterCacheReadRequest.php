<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use PhpStreamIpc\Message\Message;

interface MasterCacheReadRequest extends Message
{
    /**
     * @param array<string, mixed> $storage
     */
    public function doRead(array &$storage): MasterCacheReadResponse;
}
