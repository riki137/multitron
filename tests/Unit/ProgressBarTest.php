<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Console\ProgressBar;
use PHPUnit\Framework\TestCase;

final class ProgressBarTest extends TestCase
{
    public function testRenderStandardWidth(): void
    {
        $zero = ProgressBar::render(0, 10);
        $this->assertSame('<fg=green;bg=gray>          </><fg=white>    0%</>', $zero);

        $half = ProgressBar::render(50, 10);
        $this->assertSame('<fg=green;bg=gray>█████     </><fg=white>   50%</>', $half);

        $over = ProgressBar::render(125, 10);
        $this->assertSame('<fg=green;bg=gray>██████████</><fg=white>  125%</>', $over);
    }

    public function testRenderOverHundredPercent(): void
    {
        $part = ProgressBar::render(6.25, 8);
        $this->assertSame('<fg=green;bg=gray>▌       </><fg=white>    6%</>', $part);
    }
}
