<?php

declare(strict_types=1);

namespace Multitron\Output\Table;

use Multitron\Comms\Data\Message\LogLevel;
use Multitron\Comms\Data\Message\TaskProgress;
use Multitron\Process\TaskRunner;

final class TaskTable
{
    public array $startTimes = [];

    public int $ramTotal = 1;

    public int $ramUsed = 0;

    private TaskProgress $summary;

    private int $memoryMax = 0;
    private int $taskWidth;

    public function __construct(TaskRunner $runner)
    {
        $this->summary = new TaskProgress(0);
        $this->taskWidth = 16;
        foreach ($runner->getNodes() as $node) {
            $this->taskWidth = max(strlen($node->getId()), $this->taskWidth);
            $this->summary->total++;
        }

        $this->startTimes['TOTAL'] = microtime(true);
    }

    private static function getPrintTime(): string
    {
        return '<fg=gray>('.date('H:i:s').')</>';
    }

    public function getRow(string $taskId, TaskProgress $progress, ?int $exitCode = null): string
    {
        $percent = null;
        if ($exitCode === 0 && $progress->total === 0 && $progress->done === 0) {
            $percent = 100;
        }

        return implode(' ', array_filter([
            $this->getRowLabel($taskId, $exitCode),
            self::getProgressBar($progress, $percent),
            self::getCount($progress),
            $this->getTime($taskId),
            self::getMemoryUsageStatus($progress),
            self::getErrorStatus($progress),
            self::getWarningStatus($progress),
            self::getSkippedStatus($progress),
        ]));
    }

    public function getSummaryRow(float $done): string
    {
        $this->summary->done = (int)$done;
        return implode(' ', array_filter([
            $this->getRowLabel('TOTAL'),
            self::getProgressBar($this->summary, (fdiv($done * 100, $this->summary->total)), 'blue'),
            self::getCount($this->summary),
            $this->getTime('TOTAL', 'yellow;options=bold'),
        ]));
    }

    public function getMemoryRow(int $memorySum): string
    {
        $used = $this->ramUsed;
        $total = $this->ramTotal;
        if ($used > $total * 0.9) {
            $color = 'red';
        } elseif ($used > $total * 0.75) {
            $color = 'yellow';
        } else {
            $color = 'green';
        }

        $mainUsage = memory_get_usage(true);
        $this->memoryMax = max($this->memoryMax, $memorySum + $mainUsage);

        return implode(' ', [
            $this->getRowLabel('RAM'),
            "<fg=$color>" . TaskProgress::formatMemoryUsage($used) . '</><fg=gray>/</>' . TaskProgress::formatMemoryUsage($total),
            '<fg=gray>MAIN</><fg=blue;options=bold>' . TaskProgress::formatMemoryUsage($mainUsage) . '</>',
            '<fg=gray>SUM</><fg=magenta;options=bold>' . TaskProgress::formatMemoryUsage($memorySum + $mainUsage) . '</>',
            '<fg=gray>PEAK</><fg=red;options=bold>' . TaskProgress::formatMemoryUsage($this->memoryMax) . '</></>',
        ]);
    }

    private static function getMemoryUsageStatus(TaskProgress $progress): ?string
    {
        return $progress->memoryUsage !== null ? "<fg=blue>{$progress->getMemoryUsage()}</>" : null;
    }

    private static function getSkippedStatus(TaskProgress $progress): ?string
    {
        return $progress->skipped > 0 ? "<fg=yellow>{$progress->skipped}xSKIP</>" : null;
    }

    private static function getWarningStatus(TaskProgress $progress): ?string
    {
        return $progress->warning > 0 ? "<fg=yellow>{$progress->warning}xWARN</>" : null;
    }

    private static function getErrorStatus(TaskProgress $progress): ?string
    {
        return $progress->error > 0 ? "<fg=red>{$progress->error}xERR</>" : null;
    }

    private static function getProgressBar(TaskProgress $progress, float $percent = null, string $barColor = 'green'): string
    {
        $percent ??= $progress->getPercentage();
        $textColor = 'white';

        if ($progress->warning > 0) {
            $barColor = 'yellow';
        }

        if ($progress->done === $progress->total && $progress->done > 0) {
            $textColor = $barColor . ';options=bold';
        }

        return ProgressBar::render($percent, 16, $barColor, $textColor);
    }

    private static function getCount(TaskProgress $progress): string
    {
        $done = str_pad("{$progress->done}", max(6, strlen("{$progress->total}")), ' ', STR_PAD_LEFT);
        $total = str_pad("{$progress->total}", max(6, strlen("{$progress->total}")));
        if ($progress->done > $progress->total) {
            $done = "<fg=yellow>$done</>";
        }

        return "$done<fg=gray>/</><options=bold>$total</>";
    }

    private function getTime(string $taskId, string $color = 'white'): string
    {
        $startTime = $this->startTimes[$taskId];
        $time = microtime(true) - $startTime;
        $minutes = floor($time / 60);
        $seconds = fmod($time, 60);
        if ($minutes >= 10) {
            $out = sprintf('%d:%d', $minutes, $seconds);
        } elseif ($minutes > 0) {
            $out = sprintf('%dm%ds', $minutes, $seconds);
        } else {
            $out = number_format($seconds, 1) . 's';
        }
        $out = str_pad($out, 5, ' ', STR_PAD_LEFT);
        return "<fg=$color>" . $out . '</>';
    }

    public function getRowLabel(string $label, ?int $exitCode = null): string
    {
        $exit = '  ';
        if ($exitCode === 0) {
            $exit = ' <fg=green>✔</>';
        } elseif ($exitCode !== null) {
            $exit = ' <fg=red>✘</>';
        }
        return '<options=bold>' . str_pad($label, $this->taskWidth, ' ', STR_PAD_LEFT) . '</>'.$exit;
    }

    public function getLog(?string $taskId, string $message, LogLevel $level): string
    {
        $message = str_replace("\n", "\n" . str_repeat(' ', $this->taskWidth + 3), $message);

        if ($taskId !== null && $taskId !== '') {
            $taskId = str_pad($taskId, $this->taskWidth, ' ', STR_PAD_LEFT);
            $message = "<fg={$level->toColor()};options=bold>$taskId</>:  $message";
        }

        return $message.' '.self::getPrintTime();
    }
}
