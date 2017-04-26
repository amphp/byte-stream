<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\SizeExceededException;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\Promise;
use Amp\Stream;
use Amp\StreamIterator;
use function Amp\ByteStream\readAll;

class ReadAllTest extends TestCase {
    private $stream;

    public function setUp() {
        $stream = Stream\fromIterable(["a", "b", "c"], 5);

        $this->stream = new class($stream) implements ReadableStream {
            private $iterator;

            public function __construct(Stream $stream) {
                $this->iterator = new StreamIterator($stream);
            }

            public function advance(): Promise {
                return $this->iterator->advance();
            }

            public function getChunk(): string {
                return $this->iterator->getCurrent();
            }
        };
    }

    public function testReadAllIsComplete() {
        Loop::run(function () use (&$result) {
            $result = yield readAll($this->stream);
        });

        $this->assertSame("abc", $result);
    }

    public function testReadAllOverMaxLength() {
        $this->expectException(SizeExceededException::class);

        Loop::run(function () {
            yield readAll($this->stream, 2);
        });
    }
}
