<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use Multitron\Execution\Execution;
use StreamIpc\IpcSession;

class DummyExecution implements Execution
{
    public function __construct(
        private IpcSession $session,
        private ?int $exitCode = 0,
        private array $killResult = ['exitCode' => 0, 'stdout' => '', 'stderr' => '']
    ) {
    }

    public function getSession(): IpcSession
    {
        return $this->session;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function kill(): array
    {
        return $this->killResult;
    }
}

