<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;

class OrchestratorPeer extends IpcPeer
{
    public IpcSession $session;

    public function __construct()
    {
        parent::__construct();
        $this->session = $this->createSession(new OrchestratorTransport());
    }

    public function makeSession(): IpcSession
    {
        return $this->createSession(new OrchestratorTransport());
    }

    public function tick(?float $timeout = null): void
    {
    }
}
