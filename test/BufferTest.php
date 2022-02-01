<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;

final class BufferTest extends AsyncTestCase
{
    private ReadableStream $stream;

    public function setUp(): void
    {
        parent::setUp();
        $this->stream = new ReadableIterableStream(["abc", "def", "g"]);
    }

    public function testBuffer(): void
    {
        self::assertSame("abcdefg", buffer($this->stream));
    }

    public function testBufferLimit(): void
    {
        try {
            buffer($this->stream, limit: 4);
        } catch (BufferException $exception) {
            self::assertSame("abcdef", $exception->getBuffer());
        }
    }
}
