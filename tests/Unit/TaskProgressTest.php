<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Message\TaskProgress;
use PHPUnit\Framework\TestCase;

final class TaskProgressTest extends TestCase
{
    public function testPercentageAndOccurrences(): void
    {
        $p = new TaskProgress();
        $this->assertSame(0.0, $p->toFloat());
        $this->assertSame(0.0, $p->getPercentage());

        $p->total = 5;
        $p->done = 2;
        $this->assertSame(0.4, $p->toFloat());
        $this->assertSame(40.0, $p->getPercentage());

        $p->addOccurrence('foo', 2);
        $p->addOccurrence('FOOBAR', 1); // same key
        $this->assertSame(['FOO' => 2, 'FOOB' => 1], $p->occurrences);

        $p->setOccurrence('foo', 5);
        $this->assertSame(['FOO' => 5, 'FOOB' => 1], $p->occurrences);

        $p->setOccurrence('foo', 0);
        $this->assertSame(['FOOB' => 1], $p->occurrences);
    }

    public function testFormatMemoryUsage(): void
    {
        $this->assertSame('1.0MB', TaskProgress::formatMemoryUsage(1 * 1024 * 1024));
        $this->assertSame('10MB', TaskProgress::formatMemoryUsage(10 * 1024 * 1024));
        $this->assertSame('1.5GB', TaskProgress::formatMemoryUsage((int)(1.5 * 1024 * 1024 * 1024)));
    }
}
