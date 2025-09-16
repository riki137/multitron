<?php
declare(strict_types=1);

namespace Multitron\Orchestrator\System {
    final class MemoryInfoTestHarness
    {
        /** @var array<string, ?string> */
        public static array $files = [];

        public static ?int $memoryUsage = null;

        public static function reset(): void
        {
            self::$files = [];
            self::$memoryUsage = null;
        }

        public static function mockFile(string $path, ?string $contents): void
        {
            self::$files[$path] = $contents;
        }
    }

    function is_readable(string $filename): bool
    {
        if (\array_key_exists($filename, MemoryInfoTestHarness::$files)) {
            return MemoryInfoTestHarness::$files[$filename] !== null;
        }

        return \is_readable($filename);
    }

    function file_get_contents(string $filename, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null): string|false
    {
        if (\array_key_exists($filename, MemoryInfoTestHarness::$files)) {
            $content = MemoryInfoTestHarness::$files[$filename];

            return $content ?? false;
        }

        return \file_get_contents($filename, $use_include_path, $context, $offset, $length);
    }

    function memory_get_usage(bool $real_usage = false): int|false
    {
        if (MemoryInfoTestHarness::$memoryUsage !== null) {
            return MemoryInfoTestHarness::$memoryUsage;
        }

        return \memory_get_usage($real_usage);
    }
}

namespace Multitron\Tests\Unit {
    use Multitron\Orchestrator\System\MemoryInfo;
    use Multitron\Orchestrator\System\MemoryInfoTestHarness;
    use PHPUnit\Framework\TestCase;

    final class MemoryInfoTest extends TestCase
    {
        protected function tearDown(): void
        {
            MemoryInfoTestHarness::reset();
        }

        public function testProcessBytesReturnsNonNegativeInteger(): void
        {
            $bytes = MemoryInfo::processBytes();

            $this->assertIsInt($bytes);
            $this->assertGreaterThanOrEqual(0, $bytes);
        }

        public function testTotalBytesFallsBackToMeminfo(): void
        {
            MemoryInfoTestHarness::reset();
            MemoryInfoTestHarness::mockFile('/sys/fs/cgroup/memory.max', null);
            MemoryInfoTestHarness::mockFile('/sys/fs/cgroup/memory/memory.limit_in_bytes', null);
            MemoryInfoTestHarness::mockFile('/proc/meminfo', "MemTotal: 2048 kB\nMemAvailable: 1024 kB\n");

            $total = MemoryInfo::totalBytes();

            $this->assertSame(2048 * 1024, $total);
        }

        public function testUsedBytesFallsBackToMeminfo(): void
        {
            MemoryInfoTestHarness::reset();
            MemoryInfoTestHarness::mockFile('/sys/fs/cgroup/memory.max', null);
            MemoryInfoTestHarness::mockFile('/sys/fs/cgroup/memory.current', null);
            MemoryInfoTestHarness::mockFile('/sys/fs/cgroup/memory/memory.limit_in_bytes', null);
            MemoryInfoTestHarness::mockFile('/sys/fs/cgroup/memory/memory.usage_in_bytes', null);
            MemoryInfoTestHarness::mockFile('/proc/meminfo', "MemTotal: 4096 kB\nMemAvailable: 1024 kB\n");

            $used = MemoryInfo::usedBytes();

            $this->assertSame((4096 - 1024) * 1024, $used);
        }
    }
}
