<?php

declare(strict_types=1);

namespace Multitron\Output;

class ProgressBar
{
    private const CHARS = [' ', '▏', '▎', '▍', '▌', '▋', '▊', '▉', '█'];

    public static function render(float $percent, int $width, string $color = 'green'): string
    {
        $percent = min($percent, 100);
        $fullBlocks = (int)floor($percent / 100 * $width);
        $partialBlock = fmod($percent / 100 * $width, 1);

        return "<fg=$color;bg=gray>" .
            str_repeat(self::CHARS[8], $fullBlocks) .
            ($partialBlock > 0 ? self::CHARS[round($partialBlock * 8)] : '') .
            str_repeat(' ', $width - $fullBlocks - ($partialBlock > 0 ? 1 : 0)) .
            '</>' .
            str_pad(number_format($percent), 5, ' ', STR_PAD_LEFT) . '%';
    }
}
