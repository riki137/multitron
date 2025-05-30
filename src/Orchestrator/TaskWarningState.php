<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Generator;
use Multitron\Message\TaskWarningStateMessage;

class TaskWarningState
{
    public const WARNING_LIMIT = 10;

    private array $warnings = [];

    private array $warningCount = [];

    /**
     * @return Generator<array{messages: list<string>, count: int}>
     */
    public function fetchWarnings(): Generator
    {
        foreach ($this->warnings as $key => $warnings) {
            yield ['messages' => $warnings, 'count' => $this->warningCount[$key]];
        }
    }

    public function warningKey(string $warning): string
    {
        return preg_replace('/[^A-Za-z]/', '', $warning);
    }

    public function addWarning(string $warning, int $count): void
    {
        $key = $this->processWarning($warning);
        $this->warningCount[$key] ??= 0;
        $this->warningCount[$key] += $count;
    }

    public function setWarning(string $warning, int $count): void
    {
        $this->warningCount[$this->processWarning($warning)] = $count;
    }

    private function processWarning(string $warning): string
    {
        $key = $this->warningKey($warning);
        if (!isset($this->warnings[$key])) {
            $this->warnings[$key] = [$warning];
        } elseif (count($this->warnings[$key]) < self::WARNING_LIMIT && !in_array($warning, $this->warnings[$key])) {
            $this->warnings[$key][] = $warning;
        }
        return $key;
    }

    public function fromMessage(TaskWarningStateMessage $message): void
    {
        $this->warnings = $message->warnings;
        $this->warningCount = $message->warningCount;
    }

    public function toMessage(): TaskWarningStateMessage
    {
        return new TaskWarningStateMessage($this->warnings, $this->warningCount);
    }
}
