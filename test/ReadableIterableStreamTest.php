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

        self::assertSame(\implode($values), buffer($stream));
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
            foreach ($stream as $chunk) {
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

    public function provideEngineIterables(): iterable
    {
        yield 'array' => [['abc']];
        yield 'iterator' => [new \ArrayIterator(['abc'])];
        yield 'generator' => [(static fn () => yield 'abc')()];
    }

    /**
     * @dataProvider provideEngineIterables
     */
    public function testPhpEngineIterables(iterable $iterable): void
    {
        $stream = new ReadableIterableStream($iterable);
        self::assertSame($stream->read(), 'abc');
    }
}
