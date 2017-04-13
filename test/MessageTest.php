<?php

namespace Amp\ByteStream\Test;

use Amp\{ Emitter, Loop, Success };
use Amp\ByteStream\Message;
use Amp\PHPUnit\TestCase;

class MessageTest extends TestCase {
    public function testBufferingAll() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->resolve();

            $result = yield $message;

            $this->assertSame(\implode($values), $result);
        });
    }

    public function testFullStreamConsumption() {
        Loop::run(function () use (&$invoked) {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            Loop::delay(5, function () use ($emitter) {
                $emitter->resolve();
            });

            $buffer = "";
            while (yield $message->wait()) {
                $buffer .= $message->getChunk();
            }

            $this->assertSame(\implode($values), $buffer);
            $this->assertSame("", yield $message);
        });
    }

    public function testFastResolvingStream() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->resolve();

            $emitted = [];
            while (yield $message->wait()) {
                $emitted[] = $message->getChunk();
            }

            $this->assertSame([\implode($values)], $emitted);
            $this->assertSame(\implode($values), yield $message);
        });
    }
    public function testPartialStreamConsumption() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $buffer = "";
            for ($i = 0; $i < 1 && yield $message->wait(); ++$i) {
                $buffer .= $message->getChunk();
            }

            $this->assertSame(\array_shift($values), $buffer);

            $emitter->resolve();

            $this->assertSame(\implode($values), yield $message);
        });
    }

    public function testFailingStream() {
        Loop::run(function () {
            $exception = new \Exception;
            $value = "abc";

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            $emitter->emit($value);
            $emitter->fail($exception);

            try {
                while (yield $message->wait()) {
                    $this->assertSame($value, $message->getChunk());
                }
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        });
    }

    public function testEmptyStream() {
        Loop::run(function () {
            $value = 1;
            $message = new Message(new Success($value));

            $this->assertFalse(yield $message->wait());
        });
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The stream has resolved
     */
    public function testAdvanceAfterCompletion() {
        Loop::run(function () {
            $value = "abc";

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            $emitter->emit($value);
            $emitter->resolve();

            for ($i = 0; $i < 3; ++$i) {
                yield $message->wait();
            }
        });
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The stream has resolved
     */
    public function testGetCurrentAfterCompletion() {
        Loop::run(function () {
            $value = "abc";

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            $emitter->emit($value);
            $emitter->resolve();

            while (yield $message->wait());

            $message->getChunk();
        });
    }
}
