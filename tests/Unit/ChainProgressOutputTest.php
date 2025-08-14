<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Orchestrator\Output\ChainProgressOutput;
use Multitron\Orchestrator\Output\ProgressOutput;
use Multitron\Orchestrator\TaskState;
use PHPUnit\Framework\TestCase;

final class RecorderOutput implements ProgressOutput
{
    public int $started = 0;

    public int $updated = 0;

    public int $completed = 0;

    public int $logged = 0;

    public int $rendered = 0;

    public function onTaskStarted(TaskState $state): void
    {
        $this->started++;
    }

    public function onTaskUpdated(TaskState $state): void
    {
        $this->updated++;
    }

    public function onTaskCompleted(TaskState $state): void
    {
        $this->completed++;
    }

    public function log(TaskState $state, string $message): void
    {
        $this->logged++;
    }

    public function render(): void
    {
        $this->rendered++;
    }
}

final class ChainProgressOutputTest extends TestCase
{
    public function testForwardsCallsToAllOutputs(): void
    {
        $o1 = new RecorderOutput();
        $o2 = new RecorderOutput();
        $chain = new ChainProgressOutput($o1, $o2);
        $state = new TaskState('t1');

        $chain->onTaskStarted($state);
        $chain->onTaskUpdated($state);
        $chain->onTaskCompleted($state);
        $chain->log($state, 'msg');
        $chain->render();

        foreach ([$o1, $o2] as $rec) {
            $this->assertSame(1, $rec->started);
            $this->assertSame(1, $rec->updated);
            $this->assertSame(1, $rec->completed);
            $this->assertSame(1, $rec->logged);
            $this->assertSame(1, $rec->rendered);
        }
    }
}
