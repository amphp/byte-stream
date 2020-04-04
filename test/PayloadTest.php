<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\Payload;
use Amp\ByteStream\PendingReadError;
use Amp\Emitter;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;

class PayloadTest extends AsyncTestCase
{
    public function testBufferingAll()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        $this->assertSame(\implode($values), yield $stream->buffer());
    }

    public function testFullStreamConsumption()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        Loop::delay(5, function () use ($emitter) {
            $emitter->complete();
        });

        $buffer = "";
        while (($chunk = yield $stream->read()) !== null) {
            $buffer .= $chunk;
        }

        $this->assertSame(\implode($values), $buffer);
        $this->assertSame("", yield $stream->buffer());
    }

    public function testFastResolvingStream()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        $emitted = [];
        while (($chunk = yield $stream->read()) !== null) {
            $emitted[] = $chunk;
        }

        $this->assertSame($values, $emitted);
        $this->assertSame("", yield $stream->buffer());
    }

    public function testFastResolvingStreamBufferingOnly()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        $this->assertSame(\implode($values), yield $stream->buffer());
    }

    public function testPartialStreamConsumption()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        $emitter->emit($values[0]);

        $chunk = yield $stream->read();

        $this->assertSame(\array_shift($values), $chunk);

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        $this->assertSame(\implode($values), yield $stream->buffer());
    }

    public function testFailingStream()
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        $emitter->emit($value);
        $emitter->fail($exception);

        $callable = $this->createCallback(1);

        try {
            while (($chunk = yield $stream->read()) !== null) {
                $this->assertSame($value, $chunk);
            }

            $this->fail("No exception has been thrown");
        } catch (TestException $reason) {
            $this->assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testFailingStreamWithPendingRead()
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        $readPromise = $stream->read();
        $emitter->fail($exception);

        $callable = $this->createCallback(1);

        try {
            yield $readPromise;

            $this->fail("No exception has been thrown");
        } catch (TestException $reason) {
            $this->assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testEmptyStream()
    {
        $emitter = new Emitter;
        $emitter->complete();
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        $this->assertNull(yield $stream->read());
    }

    public function testEmptyStringStream()
    {
        $value = "";

        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        $emitter->emit($value);

        $emitter->complete();

        $this->assertSame("", yield $stream->buffer());
    }

    public function testReadAfterCompletion()
    {
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        $emitter->emit($value);
        $emitter->complete();

        $this->assertSame($value, yield $stream->read());
        $this->assertNull(yield $stream->read());
    }

    public function testPendingRead()
    {
        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));

        Loop::delay(0, function () use ($emitter) {
            $emitter->emit("test");
        });

        $this->assertSame("test", yield $stream->read());
    }

    public function testPendingReadError()
    {
        $emitter = new Emitter;
        $stream = new Payload(new IteratorStream($emitter->iterate()));
        $stream->read();

        $this->expectException(PendingReadError::class);

        $stream->read();
    }

    public function testReadAfterBuffer()
    {
        $stream = new Payload(new InMemoryStream("test"));
        $stream->buffer();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Cannot stream message data once a buffered message has been requested");

        yield $stream->read();
    }

    public function testFurtherCallsToBufferReturnSameData()
    {
        $data = "test";
        $stream = new Payload(new InMemoryStream($data));
        $this->assertSame($data, yield $stream->buffer());
        $this->assertSame($data, yield $stream->buffer());
    }
}
