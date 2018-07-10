<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ResourceOutputStream;
use PHPUnit\Framework\TestCase;

class ResourceOutputStreamTest extends TestCase {
    public function testGetResource(): void
    {
        $stream = new ResourceOutputStream(\STDOUT);

        $this->assertSame(\STDOUT, $stream->getResource());
    }

    public function testNonStream(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a valid stream");

        new ResourceOutputStream(42);
    }

    public function testNotWritable(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a writable stream");

        new ResourceOutputStream(\STDIN);
    }
}
