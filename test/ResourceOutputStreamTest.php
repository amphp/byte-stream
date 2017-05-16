<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ResourceOutputStream;
use PHPUnit\Framework\TestCase;

class ResourceOutputStreamTest extends TestCase {
    public function testGetResource() {
        $stream = new ResourceOutputStream(\STDOUT);

        $this->assertSame(\STDOUT, $stream->getResource());
    }

    public function testNonStream() {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a valid stream");

        new ResourceOutputStream(42);
    }

    public function testNotWritable() {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a writable stream");

        new ResourceOutputStream(\STDIN);
    }
}
