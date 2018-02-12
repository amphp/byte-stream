<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\Message;
use Amp\ByteStream\PendingReadError;
use Amp\Emitter;
use function Amp\GreenThread\async;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\PHPUnit\TestException;
use function Amp\Promise\wait;

class MessageTest extends TestCase {
    public function testBufferingAll() {
        wait(async(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $this->assertSame(\implode($values), $stream);
        }));
    }

    public function testFullStreamConsumption() {
        wait(async(function () use (&$invoked) {
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
            while (($chunk = $stream->read()) !== null) {
                $buffer .= $chunk;
            }

            $this->assertSame(\implode($values), $buffer);
            $this->assertSame("", $stream);
        }));
    }

    public function testFastResolvingStream() {
        wait(async(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $emitted = [];
            while (($chunk = $stream->read()) !== null) {
                $emitted[] = $chunk;
            }

            $this->assertSame($values, $emitted);
            $this->assertSame("", $stream);
        }));
    }

    public function testFastResolvingStreamBufferingOnly() {
        wait(async(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $this->assertSame(\implode($values), $stream);
        }));
    }

    public function testPartialStreamConsumption() {
        wait(async(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            $emitter->emit($values[0]);

            $chunk = $stream->read();

            $this->assertSame(\array_shift($values), $chunk);

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $this->assertSame(\implode($values), $stream);
        }));
    }

    public function testFailingStream() {
        wait(async(function () {
            $exception = new TestException;
            $value = "abc";

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            $emitter->emit($value);
            $emitter->fail($exception);

            $callable = $this->createCallback(1);

            try {
                while (($chunk = $stream->read()) !== null) {
                    $this->assertSame($value, $chunk);
                }

                $this->fail("No exception has been thrown");
            } catch (TestException $reason) {
                $this->assertSame($exception, $reason);
                $callable(); // <-- ensure this point is reached
            }
        }));
    }

    public function testFailingStreamWithPendingRead() {
        wait(async(function () {
            $exception = new TestException;
            $value = "abc";

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            $readPromise = $stream->read();
            $emitter->fail($exception);

            $callable = $this->createCallback(1);

            try {
                $readPromise;

                $this->fail("No exception has been thrown");
            } catch (TestException $reason) {
                $this->assertSame($exception, $reason);
                $callable(); // <-- ensure this point is reached
            }
        }));
    }

    public function testEmptyStream() {
        wait(async(function () {
            $emitter = new Emitter;
            $emitter->complete();
            $stream = new Message(new IteratorStream($emitter->iterate()));

            $this->assertNull($stream->read());
        }));
    }

    public function testEmptyStringStream() {
        wait(async(function () {
            $value = "";

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            $emitter->emit($value);

            $emitter->complete();

            $this->assertSame("", $stream);
        }));
    }

    public function testReadAfterCompletion() {
        wait(async(function () {
            $value = "abc";

            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            $emitter->emit($value);
            $emitter->complete();

            $this->assertSame($value, $stream->read());
            $this->assertNull($stream->read());
        }));
    }

    public function testGetInputStream() {
        wait(async(function () {
            $inputStream = new InMemoryStream("");
            $message = new Message($inputStream);

            $this->assertSame($inputStream, $message->getInputStream());
            $this->assertSame("", $message->getInputStream()->read());
        }));
    }

    public function testPendingRead() {
        wait(async(function () {
            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));

            Loop::delay(0, function () use ($emitter) {
                $emitter->emit("test");
            });

            $this->assertSame("test", $stream->read());
        }));
    }

    public function testPendingReadError() {
        wait(async(function () {
            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));
            $stream->read();

            $this->expectException(PendingReadError::class);

            $stream->read();
        }));
    }
}
