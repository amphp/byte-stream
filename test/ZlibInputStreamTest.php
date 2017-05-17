<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\ZlibInputStream;
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\Producer;

class ZlibInputStreamTest extends TestCase {
    public function testRead() {
        Loop::run(function () {
            $file1 = __DIR__ . "/fixtures/foobar.txt";
            $file2 = __DIR__ . "/fixtures/foobar.txt.gz";

            $stream = new IteratorStream(new Producer(function (callable $emit) use ($file2) {
                $content = \file_get_contents($file2);

                while ($content !== "") {
                    yield $emit($content[0]);
                    $content = \substr($content, 1);
                }
            }));

            $gzStream = new ZlibInputStream($stream, \ZLIB_ENCODING_GZIP);

            $buffer = "";
            while (($chunk = yield $gzStream->read()) !== null) {
                $buffer .= $chunk;
            }

            $this->assertSame(\file_get_contents($file1), $buffer);
        });
    }

    public function testInvalidEncoding() {
        $this->expectException(\Error::class);

        new ZlibInputStream(new InMemoryStream(""), 1337);
    }
}
