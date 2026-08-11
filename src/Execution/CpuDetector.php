<?php

declare(strict_types=1);

namespace Multitron\Execution;

use function call_user_func;
use function shell_exec;

class CpuDetector
{
    private const DEFAULT_PROCESS_BUFFER_SIZE = 4;

    private static ?int $cachedCount = null;

    /**
     * Returns the number of CPUs, caching the result.
     */
    public static function getCpuCount(): int
    {
        return self::$cachedCount ??= self::detectCpuCount();
    }

    /**
     * Detects the CPU count using various methods.
     */
    private static function detectCpuCount(): int
    {
        foreach (['pthreads_num_cpus', 'pcntl_cpu_count'] as $fn) {
            // @phpstan-ignore-next-line CI does not have this extension
            if (function_exists($fn)) {
                $count = @call_user_func($fn);
                if (is_numeric($count)) {
                    return max(1, (int)$count);
                }
            }
        }

        $env = getenv('NUMBER_OF_PROCESSORS');
        if ($env !== false && is_numeric($env)) {
            return max(1, (int)$env);
        }

        $isWindows = stripos(PHP_OS, 'WIN') === 0;
        $cmds = $isWindows
            ? ['wmic cpu get NumberOfLogicalProcessors /value']
            : ['nproc', 'getconf _NPROCESSORS_ONLN', 'sysctl -n hw.ncpu'];

        foreach ($cmds as $cmd) {
            $out = @shell_exec($cmd);
            if (preg_match('/(\d+)/', (string)$out, $m)) {
                return max(1, (int)$m[1]);
            }
        }

        return self::DEFAULT_PROCESS_BUFFER_SIZE;
    }
}
