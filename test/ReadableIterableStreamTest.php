<?php declare(strict_types=1);

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline\Queue;

final class ReadableIterableStreamTest extends AsyncTestCase
{
    public function testReadIterator(): void
    {
        $values = ["abc", "def", "ghi"];

        $source = new Queue;
        $stream = new ReadableIterableStream($source->pipe());

        foreach ($values as $value) {
            $source->pushAsync($value);
        }

        $source->complete();

        self::assertTrue($stream->isReadable());

        $buffer = "";
        while (($chunk = $stream->read()) !== null) {
            $buffer .= $chunk;
        }

        self::assertSame(\implode($values), $buffer);
        self::assertNull($stream->read());
        self::assertFalse($stream->isReadable());
    }

    public function testFailingIterator(): void
    {
        $exception = new TestException;
        $value = "abc";

        $source = new Queue;
        $stream = new ReadableIterableStream($source->pipe());

        $source->pushAsync($value);
        $source->error($exception);

        $callable = $this->createCallback(1);

        try {
            while (($chunk = $stream->read()) !== null) {
                self::assertSame($value, $chunk);
            }

            self::fail("No exception has been thrown");
        } catch (TestException $reason) {
            self::assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testThrowsOnNonStringIteration(): void
    {
        $this->expectException(StreamException::class);

        $value = 42;

        $source = new Queue;
        $stream = new ReadableIterableStream($source->pipe());

        $source->pushAsync($value);

        $stream->read();
    }

    public function testFailsAfterException(): void
    {
        $this->expectException(StreamException::class);

        $value = 42;

        $source = new Queue;
        $stream = new ReadableIterableStream($source->pipe());

        $source->pushAsync($value);

        try {
            $stream->read();
        } catch (StreamException $e) {
            $stream->read();
        }
    }

    public function testPhpEngineIterables(): void {
        $iterables = [
            ['abc'],
            new \ArrayIterator(['abc']),
            (static function () {
                yield 'abc';
            })()
        ];

        foreach ($iterables as $iterable) {
            $stream = new ReadableIterableStream($iterable);
            self::assertSame($stream->read(), 'abc');
        }
    }
}
