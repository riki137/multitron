<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use LogicException;
use Multitron\Execution\Handler\MasterCache\MasterCacheWriteKeysRequest;
use PHPUnit\Framework\TestCase;

final class MasterCacheWriteKeysRequestTest extends TestCase
{
    public function testMergeRespectsDepth(): void
    {
        $request = new MasterCacheWriteKeysRequest();
        $request->write(['a' => ['b' => 1]], 1);
        $request->write(['a' => ['b' => 2, 'c' => 3]], 2);

        $storage = [];
        $request->doWrite($storage);

        $this->assertSame(['a' => ['b' => 2, 'c' => 3]], $storage);
    }

    public function testAutoDepthDetection(): void
    {
        $request = new MasterCacheWriteKeysRequest();
        $request->write(['foo' => ['bar' => ['x' => 1]]]);
        $storage = [];
        $request->doWrite($storage);
        $this->assertSame(['foo' => ['bar' => ['x' => 1]]], $storage);
    }

    public function testDepthBelowOneThrows(): void
    {
        $request = new MasterCacheWriteKeysRequest();
        $this->expectException(LogicException::class);
        $request->write(['x' => 1], 0);
    }
}
