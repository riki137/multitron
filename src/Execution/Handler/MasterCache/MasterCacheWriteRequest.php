<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use PhpStreamIpc\Message\Message;

interface MasterCacheWriteRequest extends Message
{
    public function doWrite(array &$storage): MasterCacheWriteResponse;
}
