<?php

declare(strict_types=1);

namespace Multitron\Output\Table;

class ProgressBar
{
    private const CHARS = [' ', '▏', '▎', '▍', '▌', '▋', '▊', '▉', '█'];

    public static function render(float $percent, int $width, string $barColor = 'green', ?string $textColor = 'white'): string
    {
        $origPercent = $percent;
        $percent = min($percent, 100);
        $fullBlocks = (int)floor($percent / 100 * $width);
        $partialBlock = fmod($percent / 100 * $width, 1);

        $out = "<fg=$barColor;bg=gray>" .
            str_repeat(self::CHARS[8], $fullBlocks) .
            ($partialBlock > 0 ? self::CHARS[round($partialBlock * 8)] : '') .
            str_repeat(' ', $width - $fullBlocks - ($partialBlock > 0 ? 1 : 0)) .
            '</>';
        if ($textColor !== null) {
            $out .= "<fg=$textColor>" . str_pad(number_format($origPercent), 5, ' ', STR_PAD_LEFT) . '%</>';
        }
        return $out;
    }
}
