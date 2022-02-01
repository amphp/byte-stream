<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Pipeline;

final class BufferedReaderTest extends AsyncTestCase
{
    private const CHUNK = "Buffered String\n";
    private const REPEAT_COUNT = 10;

    private BufferedReader $bufferedReader;

    public function setUp(): void
    {
        parent::setUp();

        $pipeline = Pipeline::fromIterable(
            \array_fill(0, self::REPEAT_COUNT, self::CHUNK . self::CHUNK)
        )->delay(0.01);

        $this->bufferedReader = new BufferedReader(new IterableStream($pipeline));
    }

    public function testRead(): void
    {
        $length = \strlen(self::CHUNK);

        for ($i = 0; $i < self::REPEAT_COUNT - 1; ++$i) {
            self::assertSame(self::CHUNK, $this->bufferedReader->readLength($length));
        }

        self::assertSame(self::CHUNK, $this->bufferedReader->read());
    }

    public function testReadUntil(): void
    {
        for ($i = 0; $i < self::REPEAT_COUNT * 2 - 1; ++$i) {
            self::assertSame(self::CHUNK, $this->bufferedReader->readUntil("\n"));
        }

        self::assertSame(self::CHUNK, $this->bufferedReader->readUntil("\n"));
    }

    public function testReadUntilDelimiterNotFound(): void
    {
        $this->expectException(BufferException::class);
        $this->expectExceptionMessage('delimiter');
        $this->bufferedReader->readUntil("\r");
    }

    public function testReadUntilLimitExceeded(): void
    {
        $this->expectException(BufferException::class);
        $this->expectExceptionMessage('exceeded');
        $this->bufferedReader->readUntil("\r", limit: \strlen(self::CHUNK));
    }

    public function testReadLength(): void
    {
        $length = \strlen(self::CHUNK);

        for ($i = 0; $i < self::REPEAT_COUNT * 2; ++$i) {
            self::assertSame(self::CHUNK, $this->bufferedReader->readLength($length));
        }

        self::assertNull($this->bufferedReader->read());
    }

    public function testReadLengthStreamClosesEarly(): void
    {
        $length = \strlen(self::CHUNK);

        for ($i = 0; $i < self::REPEAT_COUNT * 2 - 1; ++$i) {
            self::assertSame(self::CHUNK, $this->bufferedReader->readLength($length));
        }

        try {
            $this->bufferedReader->readLength($length + 1);
        } catch (BufferException $exception) {
            self::assertSame(self::CHUNK, $exception->getBuffer());
        }
    }

    public function testReadingSingleBytes(): void
    {
        $length = \strlen(self::CHUNK);

        try {
            for ($i = 0; '' !== $byte = $this->bufferedReader->readLength(1); ++$i) {
                self::assertSame(self::CHUNK[$i % $length], $byte);
            }
        } catch (BufferException $exception) {
            self::assertSame('', $exception->getBuffer());
        }
    }
}
