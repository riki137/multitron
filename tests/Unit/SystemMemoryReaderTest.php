<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output {
    function getmypid(): int|false
    {
        return \Multitron\Tests\Unit\SystemMemoryReaderTest::$pid;
    }

    function is_readable(string $filename): bool
    {
        return \Multitron\Tests\Unit\SystemMemoryReaderTest::$readableFiles[$filename] ?? false;
    }

    function file_get_contents(string $filename): string|false
    {
        return \Multitron\Tests\Unit\SystemMemoryReaderTest::$fileContents[$filename] ?? false;
    }

    function shell_exec(string $command): string
    {
        \Multitron\Tests\Unit\SystemMemoryReaderTest::$shellCommands[] = $command;
        return \Multitron\Tests\Unit\SystemMemoryReaderTest::$shellOutput;
    }

    function memory_get_usage(bool $realUsage = false): int
    {
        return \Multitron\Tests\Unit\SystemMemoryReaderTest::$memoryFallback;
    }
}

namespace Multitron\Tests\Unit {

use Multitron\Orchestrator\Output\SystemMemoryReader;
use PHPUnit\Framework\TestCase;

final class SystemMemoryReaderTest extends TestCase
{
    public static int|false $pid = 4321;

    /** @var array<string, bool> */
    public static array $readableFiles = [];

    /** @var array<string, string> */
    public static array $fileContents = [];

    /** @var string[] */
    public static array $shellCommands = [];

    public static string $shellOutput = '';

    public static int $memoryFallback = 0;

    protected function setUp(): void
    {
        $this->resetDoubles();
    }

    protected function tearDown(): void
    {
        $this->resetDoubles();
    }

    public function testCurrentProcessUsagePrefersProcStatusOverPs(): void
    {
        $statusFile = '/proc/4321/status';
        self::$readableFiles[$statusFile] = true;
        self::$fileContents[$statusFile] = "Name:\tphp\nVmRSS:\t12345 kB\n";
        self::$shellOutput = "99999\n";
        self::$memoryFallback = 777;

        $reader = new SystemMemoryReader();

        $this->assertSame(12345 * 1024, $reader->getCurrentProcessUsage());
        $this->assertSame([], self::$shellCommands);
    }

    public function testCurrentProcessUsageFallsBackToPsWhenProcIsUnavailable(): void
    {
        self::$shellOutput = "23456\n";
        self::$memoryFallback = 777;

        $reader = new SystemMemoryReader();

        $this->assertSame(23456 * 1024, $reader->getCurrentProcessUsage());
        $this->assertSame(['ps -o rss= -p 4321'], self::$shellCommands);
    }

    public function testCurrentProcessUsageFallsBackToPhpAllocatorWhenSystemLookupsFail(): void
    {
        self::$shellOutput = "ps: unrecognized option: p\n";
        self::$memoryFallback = 345678;

        $reader = new SystemMemoryReader();

        $this->assertSame(345678, $reader->getCurrentProcessUsage());
        $this->assertSame(['ps -o rss= -p 4321'], self::$shellCommands);
    }

    public function testCurrentProcessUsageFallsBackToPhpAllocatorWhenPidLookupFails(): void
    {
        self::$pid = false;
        self::$memoryFallback = 456789;

        $reader = new SystemMemoryReader();

        $this->assertSame(456789, $reader->getCurrentProcessUsage());
        $this->assertSame([], self::$shellCommands);
    }

    public function testFreeMemoryReadsMemAvailableFromProcMeminfo(): void
    {
        self::$readableFiles['/proc/meminfo'] = true;
        self::$fileContents['/proc/meminfo'] = "MemTotal: 1 kB\nMemAvailable: 2048 kB\n";

        $reader = new SystemMemoryReader();

        $this->assertSame(2048 * 1024, $reader->getFreeMemory());
    }

    public function testFreeMemoryReturnsNullWhenProcMeminfoIsUnavailable(): void
    {
        $reader = new SystemMemoryReader();

        $this->assertNull($reader->getFreeMemory());
    }

    public function testFreeMemoryReturnsNullWhenMemAvailableIsMissing(): void
    {
        self::$readableFiles['/proc/meminfo'] = true;
        self::$fileContents['/proc/meminfo'] = "MemTotal: 1 kB\nMemFree: 512 kB\n";

        $reader = new SystemMemoryReader();

        $this->assertNull($reader->getFreeMemory());
    }

    private function resetDoubles(): void
    {
        self::$pid = 4321;
        self::$readableFiles = [];
        self::$fileContents = [];
        self::$shellCommands = [];
        self::$shellOutput = '';
        self::$memoryFallback = 0;
    }
}

}
