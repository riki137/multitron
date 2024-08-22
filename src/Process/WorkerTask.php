<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\Future;
use Amp\Parallel\Worker\Execution;
use Multitron\Comms\Data\Message\Message;
use Multitron\Comms\Server\ChannelRequest;
use Multitron\Comms\Server\ChannelServer;

class WorkerTask implements RunningTask
{
    private TaskCentre $centre;

    /**
     * @param Execution<int, Message, ChannelRequest> $exec
     * @param ChannelServer $server
     */
    public function __construct(
        private readonly Execution $exec,
        ChannelServer $server
    ) {
        $this->centre = new TaskCentre($exec->getChannel(), $server);
    }

    /**
     * @return Future<int>
     */
    public function getFuture(): Future
    {
        return $this->exec->getFuture();
    }

    public function getCentre(): TaskCentre
    {
        return $this->centre;
    }
}
