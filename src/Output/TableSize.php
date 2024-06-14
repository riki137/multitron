<?php

declare(strict_types=1);

namespace Multitron\Output;

class TableSize
{
    public function getTerminalWidth(): int
    {
        $width = exec('tput cols');
        return $width ? (int)$width : 80;
    }

    public function getTerminalHeight(): int
    {
        $height = exec('tput lines');
        return $height ? (int)$height : 24;
    }
}
