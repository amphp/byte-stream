<?php

namespace Amp\ByteStream;

use Amp\Cancellation;
use Amp\Future;
use Amp\Pipeline\AsyncGenerator;
use Amp\Pipeline\Emitter;
use Amp\Pipeline\Pipeline;
use Revolt\EventLoop;

// @codeCoverageIgnoreStart
if (\strlen('…') !== 3) {
    throw new \Error(
        'The mbstring.func_overload ini setting is enabled. It must be disabled to use the stream package.'
    );
} // @codeCoverageIgnoreEnd

if (!\defined('STDOUT')) {
    \define('STDOUT', \fopen('php://stdout', 'wb'));
}

if (!\defined('STDERR')) {
    \define('STDERR', \fopen('php://stderr', 'wb'));
}

/**
 * @param ReadableStream $source
 * @param WritableStream $destination
 * @param Cancellation|null $cancellation
 *
 * @return int The number of bytes written to the destination.
 */
function pipe(ReadableStream $source, WritableStream $destination, ?Cancellation $cancellation = null): int
{
    $written = 0;

    while (($chunk = $source->read($cancellation)) !== null) {
        $written += \strlen($chunk);
        $future = $destination->write($chunk);
        $chunk = null; // free memory
        $future->await($cancellation);
    }

    return $written;
}

/**
 * Create a local stream pair where data written to the OutputStream is immediately available on the InputStream.
 * Primarily useful for testing mocks.
 *
 * @return array{ReadableStream, WritableStream}
 */
function createStreamPair(): array
{
    $emitter = new Emitter();

    return [
        new PipelineStream($emitter->asPipeline()),
        new class ($emitter) implements WritableStream {
            public function __construct(
                private Emitter $emitter
            ) {
            }

            public function write(string $data): Future
            {
                if ($this->emitter->isComplete()) {
                    return Future::error(new ClosedException('The stream is no longer writable'));
                }
                return $this->emitter->emit($data);
            }

            public function end(string $finalData = ""): Future
            {
                $future = $this->write($finalData);
                if (!$this->emitter->isComplete()) {
                    $this->emitter->complete();
                }
                return $future;
            }

            public function isWritable(): bool
            {
                return !$this->emitter->isComplete();
            }
        }
    ];
}

/**
 * @param ReadableStream $source
 *
 * @return string Entire contents of the InputStream.
 */
function buffer(ReadableStream $source): string
{
    $buffer = '';

    while (($chunk = $source->read()) !== null) {
        $buffer .= $chunk;
        $chunk = null; // free memory
    }

    return $buffer;
}

/**
 * The php://input input buffer stream for the process associated with the currently active event loop.
 *
 * @return ReadableResourceStream
 */
function getInputBufferStream(): ReadableResourceStream
{
    static $map;
    $map ??= new \WeakMap();
    return $map[EventLoop::getDriver()] ??= new ReadableResourceStream(\fopen('php://input', 'rb'));
}

/**
 * The php://output output buffer stream for the process associated with the currently active event loop.
 *
 * @return WritableResourceStream
 */
function getOutputBufferStream(): WritableResourceStream
{
    static $map;
    $map ??= new \WeakMap();
    return $map[EventLoop::getDriver()] ??= new WritableResourceStream(\fopen('php://output', 'wb'));
}

/**
 * The STDIN stream for the process associated with the currently active event loop.
 *
 * @return ReadableResourceStream
 */
function getStdin(): ReadableResourceStream
{
    static $map;
    $map ??= new \WeakMap();
    return $map[EventLoop::getDriver()] ??= new ReadableResourceStream(\STDIN);
}

/**
 * The STDOUT stream for the process associated with the currently active event loop.
 *
 * @return WritableResourceStream
 */
function getStdout(): WritableResourceStream
{
    static $map;
    $map ??= new \WeakMap();
    return $map[EventLoop::getDriver()] ??= new WritableResourceStream(\STDOUT);
}

/**
 * The STDERR stream for the process associated with the currently active event loop.
 *
 * @return WritableResourceStream
 */
function getStderr(): WritableResourceStream
{
    static $map;
    $map ??= new \WeakMap();
    return $map[EventLoop::getDriver()] ??= new WritableResourceStream(\STDERR);
}

function parseLineDelimitedJson(
    ReadableStream $stream,
    bool $associative = false,
    int $depth = 512,
    int $options = 0
): Pipeline {
    return new AsyncGenerator(static function () use ($stream, $associative, $depth, $options): \Generator {
        $reader = new LineReader($stream);

        while (null !== $line = $reader->readLine()) {
            $line = \trim($line);

            if ($line === '') {
                continue;
            }

            $data = \json_decode($line, $associative, $depth, $options);
            $error = \json_last_error();

            if ($error !== \JSON_ERROR_NONE) {
                throw new \JsonException('Failed to parse JSON: ' . \json_last_error_msg(), $error);
            }

            yield $data;
        }
    });
}
