<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Console\TableRenderer;
use Multitron\Message\TaskProgress;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Tests\Mocks\DummyTask;
use Multitron\Tree\TaskNode;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class TableRendererTest extends TestCase
{
    private function createRenderer(): TableRenderer
    {
        $task = new TaskNode('task1', fn() => new DummyTask());
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
        $ref = new ReflectionMethod(TableRenderer::class, 'getCount');
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

    public function testGetTimeFormatsMinutesAndHoursPadded(): void
    {
        $r = $this->createRenderer();
        $ref = new ReflectionMethod(TableRenderer::class, 'getTime');
        $ref->setAccessible(true);

        $this->assertStringContainsString('1m05s', $ref->invoke($r, microtime(true) - 65));
        $this->assertStringContainsString('10:05', $ref->invoke($r, microtime(true) - 605));
    }

    public function testGetLogFormatsTime(): void
    {
        $r = $this->createRenderer();
        $log = $r->getLog('task1', "hello\nworld");
        $this->assertMatchesRegularExpression('/hello\n\s+world <fg=gray>\(\d{2}:\d{2}:\d{2}\)<\/>/', $log);
    }
}

