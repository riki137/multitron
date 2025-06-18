<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Generator;
use Multitron\Message\TaskWarningStateMessage;

class TaskWarningState
{
    public const WARNING_LIMIT = 5;

    /** @var array<string, list<string>> */
    private array $warnings = [];

    /** @var array<string, int> */
    private array $warningCount = [];

    /**
     * @return Generator<array{messages: list<string>, count: int}>
     */
    public function fetchAll(): Generator
    {
        foreach ($this->warnings as $key => $warnings) {
            yield ['messages' => $warnings, 'count' => $this->warningCount[$key]];
        }
    }

    public function count(): int
    {
        return array_sum($this->warningCount);
    }

    public function key(string $warning): string
    {
        return (string)preg_replace('/[^A-Za-z]/', '', $warning) ?: $warning;
    }

    public function add(string $warning, int $count): void
    {
        $key = $this->process($warning);
        $this->warningCount[$key] ??= 0;
        $this->warningCount[$key] += $count;
    }

    public function set(string $warning, int $count): void
    {
        $this->warningCount[$this->process($warning)] = $count;
    }

    private function process(string $warning): string
    {
        $key = $this->key($warning);
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
