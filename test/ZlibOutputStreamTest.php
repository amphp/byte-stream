<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\OutputStream;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ClosedException;
use Amp\ByteStream\ZlibInputStream;
use Amp\ByteStream\ZlibOutputStream;
use Amp\Deferred;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\Producer;
use Amp\Promise;
use Amp\Success;

class ZlibOutputStreamTest extends TestCase {
    public function testWrite() {
        Loop::run(function () {
            $file1 = __DIR__ . "/fixtures/foobar.txt";
            $file2 = __DIR__ . "/fixtures/foobar.txt.gz";

            $bufferStream = new OutputBuffer();
            $outputStream = new ZlibOutputStream($bufferStream, \ZLIB_ENCODING_GZIP);

            $fileStream = new ResourceInputStream(fopen($file1, "r"));
            while (($chunk = yield $fileStream->read()) !== null) {
                yield $outputStream->write($chunk);
            }

            yield $outputStream->end();

            $inputStream = new ZlibInputStream(new InMemoryStream(yield $bufferStream), \ZLIB_ENCODING_GZIP);

            $buffer = "";
            while (($chunk = yield $inputStream->read()) !== null) {
                $buffer .= $chunk;
            }

            $this->assertSame(\file_get_contents($file1), $buffer);
        });
    }

    public function testThrowsOnWritingToClosedContext() {
        $this->expectException(ClosedException::class);

        Loop::run(function () {
            $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);
            $gzStream->end("foo");
            $gzStream->write("bar");
        });
    }

    public function testThrowsOnEndingToClosedContext() {
        $this->expectException(ClosedException::class);

        Loop::run(function () {
            $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);
            $gzStream->end("foo");
            $gzStream->end("bar");
        });
    }

    public function testGetEncoding() {
        $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);

        $this->assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding() {
        $this->expectException(StreamException::class);

        new ZlibOutputStream(new OutputBuffer(), 1337);
    }

    public function testGetOptions() {
        $options = [
            "level" => -1,
            "memory" => 8,
            "window" => 15,
            "strategy" => \ZLIB_DEFAULT_STRATEGY,
        ];

        $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP, $options);

        $this->assertSame($options, $gzStream->getOptions());
    }

    public function testInvalidOptions() {
        $this->expectException(StreamException::class);

        new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP, ["level" => 42]);
    }
}

class OutputBuffer implements OutputStream, Promise {
    /** @var \Amp\Deferred|null */
    private $deferred;

    /** @var string */
    private $contents;

    private $closed = false;

    public function __construct() {
        $this->deferred = new Deferred;
    }

    public function write(string $data): Promise {
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed.");
        }

        $this->contents .= $data;

        return new Success(\strlen($data));
    }

    public function end(string $finalData = ""): Promise {
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed.");
        }

        $this->contents .= $finalData;
        $this->closed = true;

        $this->deferred->resolve($this->contents);
        $this->contents = "";

        return new Success(\strlen($finalData));
    }

    public function onResolve(callable $onResolved) {
        $this->deferred->promise()->onResolve($onResolved);
    }
}
