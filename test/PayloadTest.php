<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline\Queue;
use Revolt\EventLoop;
use function Amp\async;

final class PayloadTest extends AsyncTestCase
{
    public function testBufferingAll(): void
    {
        $values = ["abc", "def", "ghi"];

        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        foreach ($values as $value) {
            $queue->pushAsync($value)->ignore();
        }

        $queue->complete();

        self::assertSame(\implode($values), $stream->buffer());
    }

    public function testFullStreamConsumption(): void
    {
        $values = ["abc", "def", "ghi"];

        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        foreach ($values as $value) {
            $queue->pushAsync($value)->ignore();
        }

        EventLoop::delay(0.005, function () use ($queue) {
            $queue->complete();
        });

        $buffer = "";
        while (($chunk = $stream->read()) !== null) {
            $buffer .= $chunk;
        }

        self::assertSame(\implode($values), $buffer);
    }

    public function testFastResolvingStream(): void
    {
        $values = ["abc", "def", "ghi"];

        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        foreach ($values as $value) {
            $queue->pushAsync($value)->ignore();
        }

        $queue->complete();

        $emitted = [];
        while (($chunk = $stream->read()) !== null) {
            $emitted[] = $chunk;
        }

        self::assertSame($values, $emitted);
    }

    public function testFastResolvingStreamBufferingOnly(): void
    {
        $values = ["abc", "def", "ghi"];

        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        foreach ($values as $value) {
            $queue->pushAsync($value)->ignore();
        }

        $queue->complete();

        self::assertSame(\implode($values), $stream->buffer());
    }

    public function testPartialStreamConsumption(): void
    {
        $values = ["abc", "def", "ghi"];

        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        $queue->pushAsync($values[0])->ignore();

        $chunk = $stream->read();

        self::assertSame(\array_shift($values), $chunk);

        foreach ($values as $value) {
            $queue->pushAsync($value)->ignore();
        }

        $queue->complete();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Can't buffer payload after calling read()");

        $stream->buffer();
    }

    public function testFailingStream(): void
    {
        $exception = new TestException;
        $value = "abc";

        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        $queue->pushAsync($value)->ignore();
        $queue->error($exception);

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

    public function testFailingStreamWithPendingRead(): void
    {
        $exception = new TestException;
        $value = "abc";

        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        $readFuture = async(fn () => $stream->read());
        $queue->error($exception);

        $callable = $this->createCallback(1);

        try {
            $readFuture->await();

            self::fail("No exception has been thrown");
        } catch (TestException $reason) {
            self::assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testEmptyStream(): void
    {
        $queue = new Queue;
        $queue->complete();
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        self::assertNull($stream->read());
    }

    public function testEmptyStringStream(): void
    {
        $value = "";

        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        $queue->pushAsync($value)->ignore();

        $queue->complete();

        self::assertSame("", $stream->buffer());
    }

    public function testReadAfterCompletion(): void
    {
        $value = "abc";

        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        $queue->pushAsync($value)->ignore();
        $queue->complete();

        self::assertSame($value, $stream->read());
        self::assertNull($stream->read());
    }

    public function testPendingRead(): void
    {
        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));

        EventLoop::delay(0, function () use ($queue) {
            $queue->pushAsync("test")->ignore();
        });

        self::assertSame("test", $stream->read());
    }

    public function testPendingReadError(): void
    {
        $queue = new Queue;
        $stream = new Payload(new ReadableIterableStream($queue->pipe()));
        async(fn () => $stream->read());

        $this->expectException(PendingReadError::class);

        try {
            async(fn () => $stream->read())->await();
        } finally {
            $queue->complete();
        }
    }

    public function testReadAfterBuffer(): void
    {
        $stream = new Payload(new ReadableBuffer("test"));
        $stream->buffer();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Can't stream payload after calling buffer()");

        $stream->read();
    }

    public function testFurtherCallsToBufferThrows(): void
    {
        $data = "test";
        $stream = new Payload(new ReadableBuffer($data));
        self::assertSame($data, $stream->buffer());

        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Can't buffer() a payload more than once");

        self::assertSame($data, $stream->buffer());
    }

    public function testStringAsStream(): void
    {
        $data = "test";
        $stream = new Payload($data);
        self::assertSame($data, $stream->read());
        self::assertNull($stream->read());

        $stream = new Payload($data);
        self::assertSame($data, $stream->buffer());
    }
}
