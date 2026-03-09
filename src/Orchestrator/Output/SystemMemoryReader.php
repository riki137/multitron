<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

final class SystemMemoryReader
{
    public function getCurrentProcessUsage(): int
    {
        $pid = getmypid();
        if ($pid !== false) {
            $rss = $this->procMemoryUsage($pid) ?? $this->psMemoryUsage($pid);
            if ($rss !== null) {
                return $rss;
            }
        }

        return memory_get_usage(true);
    }

    public function getFreeMemory(): ?int
    {
        if (!is_readable('/proc/meminfo')) {
            return null;
        }

        $data = file_get_contents('/proc/meminfo');
        if (preg_match('/MemAvailable:\s+(\d+)/', (string)$data, $m)) {
            return (int)$m[1] * 1024;
        }

        return null;
    }

    private function procMemoryUsage(int $pid): ?int
    {
        $statusFile = '/proc/' . $pid . '/status';
        if (!is_readable($statusFile)) {
            return null;
        }

        $data = file_get_contents($statusFile);
        if (!is_string($data) || $data === '') {
            return null;
        }

        if (preg_match('/^VmRSS:\s+(\d+)\s+kB$/mi', $data, $m)) {
            return (int)$m[1] * 1024;
        }

        return null;
    }

    private function psMemoryUsage(int $pid): ?int
    {
        $out = @shell_exec('ps -o rss= -p ' . $pid);
        if (preg_match('/^\s*(\d+)\s*$/', (string)$out, $m)) {
            return (int)$m[1] * 1024;
        }

        return null;
    }
}
