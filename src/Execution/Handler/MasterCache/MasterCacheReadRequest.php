<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use PhpStreamIpc\Message\Message;

interface MasterCacheReadRequest extends Message
{
    public function doRead(array &$storage): MasterCacheReadResponse;
}
