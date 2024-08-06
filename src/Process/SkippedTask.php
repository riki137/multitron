<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\Future;
use Multitron\Comms\Server\ChannelServer;

class SkippedTask implements RunningTask
{
    public const EXIT_CODE = 512;

    private TaskCentre $centre;

    public function __construct(ChannelServer $server)
    {
        $this->centre = new TaskCentre(null, $server);
        $this->centre->getProgress()->skipped++;
    }

    public function getFuture(): Future
    {
        return Future::complete(self::EXIT_CODE);
    }

    public function getCentre(): TaskCentre
    {
        return $this->centre;
    }
}
