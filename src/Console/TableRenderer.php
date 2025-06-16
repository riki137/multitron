<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Message\TaskProgress;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use Multitron\Orchestrator\TaskStatus;

final class TableRenderer
{
    private float $startTime;

    private TaskProgress $summary;

    private int $taskWidth;

    public function __construct(TaskList $taskList)
    {
        $this->summary = new TaskProgress();
        $this->taskWidth = 16;
        foreach ($taskList as $taskId => $task) {
            $this->summary->total++;
            $this->taskWidth = max($this->taskWidth, strlen($taskId));
        }

        $this->startTime = microtime(true);
    }

    public function markFinished(string $taskId): void
    {
        $this->summary->done++;
    }

    private static function getPrintTime(): string
    {
        return '<fg=gray>(' . date('H:i:s') . ')</>';
    }

    public function getRow(TaskState $state): string
    {
        $percent = null;
        $progress = $state->getProgress();
        if ($state->getStatus() === TaskStatus::SUCCESS && $progress->total === 0 && $progress->done === 0) {
            $percent = 100;
        }

        return implode(' ', array_filter([
            $this->getRowLabel($state->getTaskId(), $state->getStatus()),
            self::getProgressBar($progress, $percent),
            self::getCount($progress),
            $this->getTime($state->getStartedAt()),
            self::getMemory($progress->memoryUsage),
            self::getOccurrenceStatus($state),
        ]));
    }

    public function getSummaryRow(float $done, int $masterMem, int $workerMem): string
    {
        $this->summary->done = (int)$done;
        return implode(' ', array_filter([
            $this->getRowLabel('TOTAL', TaskStatus::RUNNING),
            self::getProgressBar($this->summary, (fdiv($done, $this->summary->total)), 'blue'),
            self::getCount($this->summary),
            $this->getTime($this->startTime, 'yellow;options=bold'),
            '<fg=blue>' . TaskProgress::formatMemoryUsage($workerMem) . '</>+' .
            '<fg=magenta>' . TaskProgress::formatMemoryUsage($masterMem) . '</>',
        ]));
    }

    private static function getOccurrenceStatus(TaskState $state): string
    {
        $progress = $state->getProgress();
        $ret = [];
        foreach ($progress->occurrences as $key => $count) {
            if ($count > 0) {
                $ret[] = "<fg=gray>{$count}x{$key}</>";
            }
        }
        $warnCount = $state->getWarnings()->count();
        if ($warnCount > 0) {
            $ret[] = "<fg=yellow>{$warnCount}x⚠️</>";
        }
        return implode(' ', $ret);
    }

    private static function getProgressBar(TaskProgress $progress, float $percent = null, string $barColor = 'green'): string
    {
        $percent ??= $progress->getPercentage();
        $textColor = 'white';

        if ($progress->done === $progress->total && $progress->done > 0) {
            $textColor = $barColor . ';options=bold';
        }

        return ProgressBar::render($percent, 16, $barColor, $textColor);
    }

    private static function getMemory(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        return '<fg=blue>' . TaskProgress::formatMemoryUsage($bytes) . '</>';
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

    private function getTime(?float $startTime, string $color = 'white'): string
    {
        if ($startTime === null) {
            return "<fg=$color>" . str_pad('-', 5) . '</>';
        }
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

    public function getRowLabel(string $label, TaskStatus $status): string
    {
        [$symbol, $color] = match ($status) {
            TaskStatus::SUCCESS => [' ✔', 'fg=green;'],
            TaskStatus::SKIP => [' ⚠', 'fg=yellow;'],
            TaskStatus::ERROR => [' ✘', 'fg=red;'],
            default => ['  ', ''],
        };
        return "<{$color}options=bold>" . str_pad($label, $this->taskWidth, ' ', STR_PAD_LEFT) . $symbol . '</>';
    }

    public function getLog(?string $taskId, string $message): string
    {
        $message = str_replace("\n", "\n" . str_repeat(' ', $this->taskWidth + 3), $message);

        if ($taskId !== null && $taskId !== '') {
            $taskId = str_pad($taskId, $this->taskWidth, ' ', STR_PAD_LEFT);
            $message = "<options=bold>$taskId</>:  $message";
        }

        return $message . ' ' . self::getPrintTime();
    }

    public function renderWarning(string $taskId, array $warning): string
    {
        $indent = str_repeat(' ', strlen((string)$warning['count']) + 6);
        return $this->getLog($taskId, '<fg=yellow>⚠️ ' . $warning['count'] . 'x</>: ' . implode(PHP_EOL . $indent, $warning['messages']));
    }
}
