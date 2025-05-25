<?php

declare(strict_types=1);

namespace Multitron\Tests\Orchestrator;

use Multitron\Orchestrator\TaskOrchestrator;
use PHPUnit\Framework\TestCase;

final class CpuDetectionTest extends TestCase
{
    public function testDetectCpuCountUsesEnvVariable(): void
    {
        putenv('NUMBER_OF_PROCESSORS=6');

        $ref = new \ReflectionClass(TaskOrchestrator::class);
        $orch = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('detectCpuCount');
        $method->setAccessible(true);
        $count = $method->invoke($orch);

        putenv('NUMBER_OF_PROCESSORS');

        $this->assertSame(6, $count);
    }
}

