<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ResourceInputStream;
use PHPUnit\Framework\TestCase;

class ResourceInputStreamTest extends TestCase {
    public function testGetResource() {
        $stream = new ResourceInputStream(\STDIN);

        $this->assertSame(\STDIN, $stream->getResource());
    }

    public function testNonStream() {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a valid stream");

        new ResourceInputStream(42);
    }

    public function testNotReadable() {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a readable stream");

        new ResourceInputStream(\STDOUT);
    }
}
