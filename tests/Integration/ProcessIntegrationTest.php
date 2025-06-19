<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Execution\Process;
use PHPUnit\Framework\TestCase;

final class ProcessIntegrationTest extends TestCase
{
    public function testProcessRunsAndOutputs(): void
    {
        $proc = new Process([PHP_BINARY, '-r', 'usleep(100000); echo "hi";']);
        $this->assertTrue($proc->isRunning());
        usleep(150000); // let the command finish
        $stdout = stream_get_contents($proc->getStdout());
        $exit = $proc->close();
        $this->assertSame('hi', $stdout);
        $this->assertSame(0, $exit);
        $this->assertSame(0, $proc->getExitCode());
    }

    public function testKillTerminatesProcess(): void
    {
        $proc = new Process([PHP_BINARY, '-r', 'sleep(5);']);
        $this->assertTrue($proc->isRunning());
        $proc->kill();
        for ($i = 0; $i < 50 && $proc->isRunning(); $i++) {
            usleep(100000);
        }
        $this->assertFalse($proc->isRunning());
        $proc->close();
    }
}
