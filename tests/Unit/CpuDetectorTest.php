<?php
namespace {
    if (!function_exists('pthreads_num_cpus')) {
        function pthreads_num_cpus() {
            return \Multitron\Tests\Unit\CpuDetectorTest::$pthreadsCount;
        }
    }
    if (!function_exists('pcntl_cpu_count')) {
        function pcntl_cpu_count() {
            return \Multitron\Tests\Unit\CpuDetectorTest::$pcntlCount;
        }
    }
}
namespace Multitron\Execution {
    function shell_exec(string $cmd) {
        return \Multitron\Tests\Unit\CpuDetectorTest::$shellOutput;
    }
}
namespace Multitron\Tests\Unit {

use Multitron\Execution\CpuDetector;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class CpuDetectorTest extends TestCase
{
    public static $pthreadsCount = null;
    public static $pcntlCount = null;
    public static $shellOutput = '';

    protected function tearDown(): void
    {
        $prop = new ReflectionProperty(CpuDetector::class, 'cachedCount');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        self::$pthreadsCount = null;
        self::$pcntlCount = null;
        self::$shellOutput = '';
        putenv('NUMBER_OF_PROCESSORS');
    }

    public function testUsesFunctionIfAvailable(): void
    {
        self::$pthreadsCount = 5;
        $this->assertSame(5, CpuDetector::getCpuCount());
    }

    public function testEnvironmentVariableFallbackAndCache(): void
    {
        putenv('NUMBER_OF_PROCESSORS=4');
        $this->assertSame(4, CpuDetector::getCpuCount());
        putenv('NUMBER_OF_PROCESSORS=10');
        $this->assertSame(4, CpuDetector::getCpuCount());
    }

    public function testResultNeverBelowOne(): void
    {
        putenv('NUMBER_OF_PROCESSORS=0');
        self::$pthreadsCount = 0;
        self::$pcntlCount = 0;
        $count = CpuDetector::getCpuCount();
        $this->assertGreaterThanOrEqual(1, $count);
    }
}


}
