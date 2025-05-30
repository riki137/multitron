<?php

declare(strict_types=1);

namespace Multitron\Tests\Handler;

use Multitron\Execution\Handler\MasterCache\MasterCacheWriteResponse;
use Multitron\Execution\Handler\MasterCache\MasterCacheWriteKeysRequest;
use PHPUnit\Framework\TestCase;

class MasterCacheWriteKeysRequestTest extends TestCase
{
    public function testSimpleWrite(): void
    {
        $storage = [];
        $response = (new MasterCacheWriteKeysRequest())
            ->write('foo', 'bar')
            ->doWrite($storage);

        $this->assertInstanceOf(MasterCacheWriteResponse::class, $response);
        $this->assertSame(['foo' => 'bar'], $storage);
    }

    public function testNestedWrite(): void
    {
        $storage = [];
        (new MasterCacheWriteKeysRequest())
            ->write('foo.bar.baz', 123)
            ->doWrite($storage);

        $this->assertSame(
            ['foo' => ['bar' => ['baz' => 123]]],
            $storage
        );
    }

    public function testMultipleWritesMergeBucket(): void
    {
        $storage = [];
        (new MasterCacheWriteKeysRequest())
            ->write('a.b', 1)
            ->write('a.c', 2)
            ->write('d',   3)
            ->doWrite($storage);

        $this->assertSame([
            'd' => 3,
            'a' => ['b' => 1, 'c' => 2],
        ], $storage);
    }

    public function testWritePrecedenceShallowThenDeep(): void
    {
        $storage = [];
        (new MasterCacheWriteKeysRequest())
            ->write('key',      'shallow')
            ->write('key.sub',  'deep')
            ->doWrite($storage);

        // deep overrides and resets the scalar
        $this->assertSame([
            'key' => ['sub' => 'deep']
        ], $storage);
    }

    public function testWritePrecedenceDeepThenShallow(): void
    {
        $storage = [];
        (new MasterCacheWriteKeysRequest())
            ->write('key.sub',  'deep')
            ->write('key',      'shallow')
            ->doWrite($storage);

        // order of calls doesn’t matter—shallow is always applied before deep
        $this->assertSame([
            'key' => ['sub' => 'deep']
        ], $storage);
    }

    public function testMergeSimple(): void
    {
        $storage = [];
        (new MasterCacheWriteKeysRequest())
            ->merge('x.y', ['a' => 1, 'b' => 2])
            ->doWrite($storage);

        $this->assertSame([
            'x' => ['y' => ['a' => 1, 'b' => 2]]
        ], $storage);
    }

    public function testMergeOverridesExistingShallow(): void
    {
        $storage = ['x' => 0];
        (new MasterCacheWriteKeysRequest())
            ->merge('x.y', ['a' => 10])
            ->doWrite($storage);

        // merging under x.y forces x to become an array
        $this->assertSame([
            'x' => ['y' => ['a' => 10]]
        ], $storage);
    }

    public function testMergeAndWriteCombined(): void
    {
        $storage = [];
        (new MasterCacheWriteKeysRequest())
            ->merge('m.n', ['p' => 5])
            ->write('m.n.q', 10)
            ->doWrite($storage);

        $this->assertSame([
            'm' => ['n' => ['p' => 5, 'q' => 10]],
        ], $storage);
    }

    public function testComplexScenario(): void
    {
        // start with some existing data
        $storage = ['existing' => ['alpha' => 100]];

        (new MasterCacheWriteKeysRequest())
            ->write('new.one',              1)
            ->merge('existing.beta',       ['gamma' => 200])
            ->write('existing.alpha.delta', 300)
            ->merge('new',                 ['nested' => ['deep' => 400]])
            ->doWrite($storage);

        $expected = [
            'existing' => [
                'alpha' => ['delta' => 300],
                'beta'  => ['gamma' => 200],
            ],
            'new'      => [
                'one'    => 1,
                'nested' => ['deep' => 400],
            ],
        ];

        $this->assertSame($expected, $storage);
    }

    public function testComplexScenarioTwo(): void
    {
        // pre-existing storage with a mix of arrays and scalars
        $storage = [
            'root'  => [
                'alpha' => ['x' => 10],
                'beta'  => 20,
            ],
            'alone' => 'yes',
        ];

        $request = new MasterCacheWriteKeysRequest();
        $request
            ->merge('root.alpha',           ['y' => 30, 'x' => 15])       // override alpha.x, add alpha.y
            ->write('root.beta.gamma',      40)                            // scalar beta → array with gamma
            ->write('alone',                ['new' => 'structure'])       // scalar alone → array
            ->merge('alone.new',            ['prop' => 'value'])          // nested under alone.new
            ->merge('root',                 ['delta' => ['z' => 50]])     // add root.delta.z
            ->write('root.delta.z',         55)                            // override delta.z
            ->doWrite($storage);

        $this->assertSame([
            'root'  => [
                'alpha' => ['x' => 15, 'y' => 30],
                'beta'  => ['gamma' => 40],
                'delta' => ['z' => 55],
            ],
            'alone' => [
                'new' => ['prop' => 'value'],
            ],
        ], $storage);
    }

    public function testComplexScenarioThree(): void
    {
        // a really messy initial state
        $storage = [
            'A' => [
                'B' => 'old',
                'C' => ['D' => 100, 'E' => 200],
            ],
            'X' => 1,
        ];

        $request = new MasterCacheWriteKeysRequest();
        $request
            ->write('A.C.F.G',       300)                             // new nested F.G under A.C
            ->merge('A.C',           ['E' => 250, 'H' => 350])        // override C.E, add C.H
            ->write('Y.Z',           400)                             // brand-new Y.Z
            ->merge('A',             ['B' => ['I' => 450], 'J' => 500])// scalar A.B→array + new J
            ->merge('X.Y.Z',         ['K' => 600])                    // scalar X→array→Y→Z→K
            ->write('X.Y.Z.K',       650)                             // override that K
            ->merge('Y',             ['W' => 700])                    // alongside Y.Z, add Y.W
            ->write('A.C.D',         999)                             // override original C.D
            ->doWrite($storage);

        $this->assertSame([
            'A' => [
                'B' => ['I' => 450],
                'C' => [
                    'D' => 999,
                    'E' => 250,
                    'H' => 350,
                    'F' => ['G' => 300],
                ],
                'J' => 500,
            ],
            'X' => [
                'Y' => [
                    'Z' => ['K' => 650],   // merge(600) then write(650)
                ],
            ],
            'Y' => [
                'Z' => 400,
                'W' => 700,
            ],
        ], $storage);
    }
}
