<?php
namespace StreamIpc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StreamIpc\Serialization\NativeMessageSerializer;
use StreamIpc\Transport\NativeFrameReader;
use StreamIpc\Transport\StreamClosedException;

final class NativeFrameReaderClosedStreamTest extends TestCase
{
    public function testStreamClosedBeforeFrameThrows(): void
    {
        $stream = fopen('php://memory', 'r');
        $reader = new NativeFrameReader($stream, new NativeMessageSerializer(), 1024);

        $this->expectException(StreamClosedException::class);
        try {
            $reader->readFrameSync();
        } finally {
            fclose($stream);
        }
    }
}
