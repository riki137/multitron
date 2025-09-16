<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\System;

/**
 * Container/Kubernetes/AWS/ECS friendly memory inspector.
 * - Never throws; public methods return int bytes or null.
 * - Prefers cgroup (v2 then v1), falls back to /proc/meminfo, then PHP.
 */
final class MemoryInfo
{
    /** Bytes in 1 KiB */
    private const KIB = 1024;

    /**
     * Memory the current PHP process uses (resident set, if possible).
     * Guaranteed non-negative int (>= 0).
     */
    public static function processBytes(): int
    {
        // Try /proc/self/status VmRSS (kB)
        $path = '/proc/self/status';
        if (is_readable($path)) {
            $data = @file_get_contents($path);
            if (is_string($data) && $data !== '') {
                if (preg_match('/^VmRSS:\s+(\d+)\s+kB/im', $data, $m) === 1) {
                    $kb = (int) $m[1];
                    if ($kb > 0) {
                        return $kb * self::KIB;
                    }
                }
            }
        }

        // Fallback: PHP's allocator (includes overhead; stable and safe)
        $bytes = @memory_get_usage(true);
        return is_int($bytes) && $bytes >= 0 ? $bytes : 0;
    }

    /**
     * Total RAM visible to the workload (cgroup limit if applicable, else MemTotal).
     * Returns null if not detectable.
     */
    public static function totalBytes(): ?int
    {
        // cgroup v2: memory.max (can be 'max' meaning unlimited)
        if (($limit = self::readCgroupV2Limit()) !== null) {
            return $limit;
        }

        // cgroup v1: memory.limit_in_bytes (often huge if effectively unlimited)
        if (($limit = self::readCgroupV1Limit()) !== null) {
            // Some runtimes set an absurdly large limit to indicate "no limit"
            // If it's > 1 PiB, treat as "no limit" and fall back to host.
            if ($limit < 1 << 50) {
                return $limit;
            }
        }

        // Host MemTotal (kB) from /proc/meminfo
        $memTotal = self::meminfoValue('MemTotal');
        if ($memTotal !== null) {
            return $memTotal;
        }

        return null;
    }

    /**
     * System/container used RAM in bytes (aligned to the same total as totalBytes()).
     * Returns null if not detectable.
     */
    public static function usedBytes(): ?int
    {
        // Prefer cgroup usage if we’ll also use a cgroup total
        $v2Usage = self::readCgroupV2Usage();
        $v2Limit = self::readCgroupV2Limit();
        if ($v2Usage !== null && $v2Limit !== null) {
            return max(0, min($v2Usage, $v2Limit));
        }

        $v1Usage = self::readCgroupV1Usage();
        $v1Limit = self::normalizedCgroupV1Limit();
        if ($v1Usage !== null && $v1Limit !== null) {
            return max(0, min($v1Usage, $v1Limit));
        }

        // Linux host fallback: Used = MemTotal - MemAvailable (both kB)
        $total = self::meminfoValue('MemTotal');
        $avail = self::meminfoValue('MemAvailable');
        if ($total !== null && $avail !== null) {
            $used = $total - $avail;
            return max($used, 0);
        }

        return null;
    }

    /**
     * System/container available RAM in bytes.
     * Returns null if not detectable.
     */
    public static function availableBytes(): ?int
    {
        // If we have both cgroup total & usage, derive available = total - usage
        $v2Usage = self::readCgroupV2Usage();
        $v2Limit = self::readCgroupV2Limit();
        if ($v2Usage !== null && $v2Limit !== null) {
            $avail = $v2Limit - $v2Usage;
            return max($avail, 0);
        }

        $v1Usage = self::readCgroupV1Usage();
        $v1Limit = self::normalizedCgroupV1Limit();
        if ($v1Usage !== null && $v1Limit !== null) {
            $avail = $v1Limit - $v1Usage;
            return max($avail, 0);
        }

        // Host MemAvailable (kB)
        $avail = self::meminfoValue('MemAvailable');
        return $avail !== null ? $avail : null;
    }

    // -------------------- internals (no throws) --------------------

    /** Read key (kB) from /proc/meminfo and return bytes */
    private static function meminfoValue(string $key): ?int
    {
        $path = '/proc/meminfo';
        if (!is_readable($path)) {
            return null;
        }
        $txt = @file_get_contents($path);
        if (!is_string($txt) || $txt === '') {
            return null;
        }
        if (preg_match('/^' . preg_quote($key, '/') . ':\s+(\d+)\s+kB$/m', $txt, $m) !== 1) {
            return null;
        }
        $kb = (int) $m[1];
        return $kb > 0 ? $kb * self::KIB : null;
    }

    /** cgroup v2: /sys/fs/cgroup/memory.max ('max' means unlimited) */
    private static function readCgroupV2Limit(): ?int
    {
        $p = '/sys/fs/cgroup/memory.max';
        if (!is_readable($p)) {
            return null;
        }
        $raw = @trim((string) @file_get_contents($p));
        if ($raw === '' || $raw === 'max') {
            return null;
        }
        // numeric
        if (ctype_digit($raw)) {
            $val = (int) $raw;
            // filter out absurd "unlimited" sentinels if any
            return $val > 0 && $val < (1 << 50) ? $val : null;
        }
        return null;
    }

    /** cgroup v2: /sys/fs/cgroup/memory.current */
    private static function readCgroupV2Usage(): ?int
    {
        $p = '/sys/fs/cgroup/memory.current';
        if (!is_readable($p)) {
            return null;
        }
        $raw = @trim((string) @file_get_contents($p));
        return ctype_digit($raw) ? (int) $raw : null;
    }

    /** cgroup v1: /sys/fs/cgroup/memory/memory.limit_in_bytes */
    private static function readCgroupV1Limit(): ?int
    {
        $p = '/sys/fs/cgroup/memory/memory.limit_in_bytes';
        if (!is_readable($p)) {
            return null;
        }
        $raw = @trim((string) @file_get_contents($p));
        return ctype_digit($raw) ? (int) $raw : null;
    }

    /** Normalize cgroup v1 limit; ignore "unlimited" giant values. */
    private static function normalizedCgroupV1Limit(): ?int
    {
        $limit = self::readCgroupV1Limit();
        if ($limit === null) {
            return null;
        }
        // Some kernels expose ~ 2^63-1 or similar for "no limit"
        return ($limit > 0 && $limit < (1 << 50)) ? $limit : null;
    }

    /** cgroup v1: /sys/fs/cgroup/memory/memory.usage_in_bytes */
    private static function readCgroupV1Usage(): ?int
    {
        $p = '/sys/fs/cgroup/memory/memory.usage_in_bytes';
        if (!is_readable($p)) {
            return null;
        }
        $raw = @trim((string) @file_get_contents($p));
        return ctype_digit($raw) ? (int) $raw : null;
    }
}
