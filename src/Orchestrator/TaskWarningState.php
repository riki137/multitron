<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use Generator;
use Multitron\Message\TaskWarningMessage;

class TaskWarningState
{
    private array $warnings = [];

    private array $warningCount = [];

    /**
     * @return Generator<array{messages: string, count: int}>
     */
    public function fetchWarnings(): Generator
    {
        foreach ($this->warnings as $key => $warnings) {
            yield ['messages' => $warnings, 'count' => $this->warningCount[$key]];
        }
    }

    public function process(TaskWarningMessage $message): void
    {
        $key = $this->warningKey($message->warning);
        $this->warnings[$key][] = $message->warning;

        if (isset($this->warningCount[$key])) {
            $this->warningCount[$key] += $message->count;
        } else {
            $this->warningCount[$key] = $message->count;
        }
    }

    public function warningKey(string $warning): string
    {
        return preg_replace('/[^A-Za-z]/i', '', $warning);
    }
}
