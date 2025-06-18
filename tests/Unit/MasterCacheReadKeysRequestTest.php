<?php
declare(strict_types=1);

namespace Multitron\Tests\Unit;

use Multitron\Execution\Handler\MasterCache\MasterCacheReadKeysRequest;
use PHPUnit\Framework\TestCase;

final class MasterCacheReadKeysRequestTest extends TestCase
{
    public function testNestedReadReturnsSubset(): void
    {
        $storage = [
            'root' => [
                'layer1' => [
                    'keep' => true,
                    'drop' => false,
                ],
                'val' => 42,
            ],
            'top' => 'value',
        ];

        $req = new MasterCacheReadKeysRequest([
            'root' => ['layer1' => ['keep']],
            'top',
        ]);

        $response = $req->doRead($storage);
        $this->assertSame([
            'root' => ['layer1' => ['keep' => true]],
            'top' => 'value',
        ], $response->data);
    }
}
