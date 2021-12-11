<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline\Emitter;
use Revolt\EventLoop;
use function Amp\async;

final class PayloadTest extends AsyncTestCase
{
    public function testBufferingAll(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value)->ignore();
        }

        $emitter->complete();

        self::assertSame(\implode($values), $stream->buffer());
    }

    public function testFullStreamConsumption(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value)->ignore();
        }

        EventLoop::delay(0.005, function () use ($emitter) {
            $emitter->complete();
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

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value)->ignore();
        }

        $emitter->complete();

        $emitted = [];
        while (($chunk = $stream->read()) !== null) {
            $emitted[] = $chunk;
        }

        self::assertSame($values, $emitted);
    }

    public function testFastResolvingStreamBufferingOnly(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value)->ignore();
        }

        $emitter->complete();

        self::assertSame(\implode($values), $stream->buffer());
    }

    public function testPartialStreamConsumption(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        $emitter->emit($values[0])->ignore();

        $chunk = $stream->read();

        self::assertSame(\array_shift($values), $chunk);

        foreach ($values as $value) {
            $emitter->emit($value)->ignore();
        }

        $emitter->complete();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Can't buffer payload after calling read()");

        $stream->buffer();
    }

    public function testFailingStream(): void
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        $emitter->emit($value)->ignore();
        $emitter->error($exception);

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

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        $readFuture = async(fn () => $stream->read());
        $emitter->error($exception);

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
        $emitter = new Emitter;
        $emitter->complete();
        $stream = new Payload(new IterableStream($emitter->pipe()));

        self::assertNull($stream->read());
    }

    public function testEmptyStringStream(): void
    {
        $value = "";

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        $emitter->emit($value)->ignore();

        $emitter->complete();

        self::assertSame("", $stream->buffer());
    }

    public function testReadAfterCompletion(): void
    {
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        $emitter->emit($value)->ignore();
        $emitter->complete();

        self::assertSame($value, $stream->read());
        self::assertNull($stream->read());
    }

    public function testPendingRead(): void
    {
        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        EventLoop::delay(0, function () use ($emitter) {
            $emitter->emit("test")->ignore();
        });

        self::assertSame("test", $stream->read());
    }

    public function testPendingReadError(): void
    {
        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));
        async(fn () => $stream->read());

        $this->expectException(PendingReadError::class);

        try {
            async(fn () => $stream->read())->await();
        } finally {
            $emitter->complete();
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
