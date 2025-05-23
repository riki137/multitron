<?php

declare(strict_types=1);

namespace Multitron\Console;

use Multitron\Message\TaskProgress;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskStatus;
use Multitron\Tree\TaskLeafNode;

final class TaskTable
{
    public array $startTimes = [];

    private TaskProgress $summary;

    private int $taskWidth;

    public function __construct(TaskList $taskList)
    {
        $this->summary = new TaskProgress();
        $this->taskWidth = 16;
        foreach ($taskList->getNodes() as $taskId => $task) {
            if (!$task instanceof TaskLeafNode) {
                continue;
            }
            $this->summary->total++;
            $this->taskWidth = max($this->taskWidth, strlen($taskId));
        }

        $this->startTimes['TOTAL'] = microtime(true);
    }

    public function markStarted(string $taskId): void
    {
        $this->startTimes[$taskId] = microtime(true);
    }

    public function markFinished(string $taskId): void
    {
        unset($this->startTimes[$taskId]);
        $this->summary->done++;
    }

    private static function getPrintTime(): string
    {
        return '<fg=gray>(' . date('H:i:s') . ')</>';
    }

    public function getRow(string $taskId, TaskProgress $progress, TaskStatus $status): string
    {
        $percent = null;
        if ($status === TaskStatus::SUCCESS && $progress->total === 0 && $progress->done === 0) {
            $percent = 100;
        }

        return implode(' ', array_filter([
            $this->getRowLabel($taskId, $status),
            self::getProgressBar($progress, $percent),
            self::getCount($progress),
            $this->getTime($taskId),
            self::getOccurenceStatus($progress),
        ]));
    }

    public function getSummaryRow(float $done): string
    {
        $this->summary->done = (int)$done;
        return implode(' ', array_filter([
            $this->getRowLabel('TOTAL', TaskStatus::RUNNING),
            self::getProgressBar($this->summary, (fdiv($done, $this->summary->total)), 'blue'),
            self::getCount($this->summary),
            $this->getTime('TOTAL', 'yellow;options=bold'),
        ]));
    }

    private static function getOccurenceStatus(TaskProgress $progress): string
    {
        $ret = [];
        foreach ($progress->occurrences as $key => $count) {
            if ($count > 0) {
                $ret[] = "<fg=gray>{$count}x{$key}</>";
            }
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
        $startTime = $this->startTimes[$taskId] ?? null;
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
}
