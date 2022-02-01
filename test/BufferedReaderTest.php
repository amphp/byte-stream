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

        $pipeline = Pipeline::concat([
            Pipeline::fromIterable(\array_fill(0, self::REPEAT_COUNT, self::CHUNK . self::CHUNK))
                ->delay(0.01),
            Pipeline::fromIterable([\trim(self::CHUNK)]),
        ]);

        $this->bufferedReader = new BufferedReader(new IterableStream($pipeline));
    }

    public function testRead(): void
    {
        $length = \strlen(self::CHUNK);

        for ($i = 0; $i < self::REPEAT_COUNT; ++$i) {
            self::assertSame(self::CHUNK, $this->bufferedReader->readFixedLength($length));
            self::assertSame(self::CHUNK, $this->bufferedReader->read());
        }

        self::assertSame(\trim(self::CHUNK), $this->bufferedReader->read());
    }

    public function testReadUntilDelimiter(): void
    {
        for ($i = 0; $i < self::REPEAT_COUNT * 2; ++$i) {
            self::assertSame(self::CHUNK, $this->bufferedReader->readUntilDelimiter("\n"));
        }

        self::assertSame(\trim(self::CHUNK), $this->bufferedReader->readUntilDelimiter("\n"));
    }

    public function testReadFixedLength(): void
    {
        $length = \strlen(self::CHUNK);

        for ($i = 0; $i < self::REPEAT_COUNT * 2; ++$i) {
            self::assertSame(self::CHUNK, $this->bufferedReader->readFixedLength($length));
        }

        self::assertSame(\trim(self::CHUNK), $this->bufferedReader->readFixedLength($length));
    }

    public function testReadingSingleBytes(): void
    {
        $length = \strlen(self::CHUNK);

        for ($i = 0; '' !== $byte = $this->bufferedReader->readFixedLength(1); ++$i) {
            self::assertSame(self::CHUNK[$i % $length], $byte);
        }
    }
}
