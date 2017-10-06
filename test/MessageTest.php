<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\Message;
use Amp\ByteStream\PendingReadError;
use Amp\Emitter;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\PHPUnit\TestException;

class MessageTest extends TestCase {
    public function testBufferingAll() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $this->assertSame(\implode($values), yield $stream);
        });
    }

    public function testFullStreamConsumption() {
        Loop::run(function () use (&$invoked) {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

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
            $this->assertSame("", yield $stream);
        });
    }

    public function testFastResolvingStream() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $emitted = [];
            while (($chunk = yield $stream->read()) !== null) {
                $emitted[] = $chunk;
            }

            $this->assertSame($values, $emitted);
            $this->assertSame("", yield $stream);
        });
    }

    public function testFastResolvingStreamBufferingOnly() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $this->assertSame(\implode($values), yield $stream);
        });
    }

    public function testPartialStreamConsumption() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            $emitter->emit($values[0]);

            $chunk = yield $stream->read();

            $this->assertSame(\array_shift($values), $chunk);

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $this->assertSame(\implode($values), yield $stream);
        });
    }

    public function testFailingStream() {
        Loop::run(function () {
            $exception = new TestException;
            $value = "abc";

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

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
        });
    }

    public function testFailingStreamWithPendingRead() {
        Loop::run(function () {
            $exception = new TestException;
            $value = "abc";

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

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
        });
    }

    public function testEmptyStream() {
        Loop::run(function () {
            $emitter = new Emitter;
            $emitter->complete();
            $stream = new Message(new IteratorStream($emitter->iterate()));

            $this->assertNull(yield $stream->read());
        });
    }

    public function testReadAfterCompletion() {
        Loop::run(function () {
            $value = "abc";

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            $emitter->emit($value);
            $emitter->complete();

            $this->assertSame($value, yield $stream->read());
            $this->assertNull(yield $stream->read());
        });
    }

    public function testGetInputStream() {
        Loop::run(function () {
            $inputStream = new InMemoryStream("");
            $message = new Message($inputStream);

            $this->assertSame($inputStream, $message->getInputStream());
            $this->assertSame("", yield $message->getInputStream()->read());
        });
    }

    public function testPendingRead() {
        Loop::run(function () {
            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            Loop::delay(0, function () use ($emitter) {
                $emitter->emit("test");
            });

            $this->assertSame("test", yield $stream->read());
        });
    }

    public function testPendingReadError() {
        Loop::run(function () {
            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));
            $stream->read();

            $this->expectException(PendingReadError::class);

            $stream->read();
        });
    }
}
