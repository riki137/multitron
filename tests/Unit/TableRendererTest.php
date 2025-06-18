<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Console\TableRenderer;
use Multitron\Message\TaskProgress;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Tree\TaskNode;
use Multitron\Execution\Task;
use Multitron\Comms\TaskCommunicator;
use PHPUnit\Framework\TestCase;

final class TableRendererTest extends TestCase
{
    private function createRenderer(): TableRenderer
    {
        $task = new TaskNode('task1', fn() => new class implements Task {
            public function execute(TaskCommunicator $comm): void {}
        });
        $root = new TaskNode('root', null, [$task]);
        return new TableRenderer(new TaskList($root));
    }

    public function testGetRowLabel(): void
    {
        $r = $this->createRenderer();
        $label = $r->getRowLabel('task1', TaskStatus::SUCCESS);
        $this->assertStringContainsString('✔', $label);
    }

    public function testGetCountHighlight(): void
    {
        $r = $this->createRenderer();
        $progress = new TaskProgress();
        $progress->total = 10;
        $progress->done = 12;
        $ref = new \ReflectionMethod(TableRenderer::class, 'getCount');
        $ref->setAccessible(true);
        $out = $ref->invoke(null, $progress);
        $this->assertStringContainsString('<fg=yellow>', $out);
    }

    public function testRenderWarningAddsEllipsis(): void
    {
        $r = $this->createRenderer();
        $warning = [
            'messages' => ['a','b','c','d','e'],
            'count' => 6,
        ];
        $out = $r->renderWarning('task1', $warning);
        $this->assertStringContainsString('⚠️ 6x', $out);
        $this->assertStringContainsString('<fg=yellow>...</>', $out);
    }

    public function testGetLogFormatsTime(): void
    {
        $r = $this->createRenderer();
        $log = $r->getLog('task1', "hello\nworld");
        $this->assertMatchesRegularExpression('/hello\n\s+world <fg=gray>\(\d{2}:\d{2}:\d{2}\)<\/>/', $log);
    }
}
