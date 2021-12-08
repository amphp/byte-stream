<?php

namespace Amp\ByteStream;

use Amp\Cancellation;
use Amp\Future;
use Amp\Pipeline\AsyncGenerator;
use Amp\Pipeline\Emitter;
use Revolt\EventLoop;
use function Amp\Pipeline\fromIterable;
use function Amp\Pipeline\map;

// @codeCoverageIgnoreStart
if (\strlen('…') !== 3) {
    throw new \Error(
        'The mbstring.func_overload ini setting is enabled. It must be disabled to use amphp/byte-stream.'
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
        $destination->write($chunk);
        $chunk = null; // free memory
    }

    return $written;
}

/**
 * Create a local stream pair where data written to the WritableStream is immediately available on the ReadableStream.
 * Primarily useful for testing mocks.
 *
 * @return array{ReadableStream, WritableStream}
 */
function createStreamPair(): array
{
    $emitter = new Emitter();

    return [
        new IterableStream($emitter->pipe()),
        new class($emitter) implements WritableStream {
            public function __construct(
                private Emitter $emitter
            ) {
            }

            public function write(string $bytes): void
            {
                if ($this->emitter->isComplete()) {
                    throw new ClosedException('The stream is no longer writable');
                }

                $this->emitter->emit($bytes)->await();
            }

            public function end(string $bytes = ""): void
            {
                $this->write($bytes);

                if (!$this->emitter->isComplete()) {
                    $this->emitter->complete();
                }
            }

            public function isWritable(): bool
            {
                return !$this->emitter->isComplete();
            }
        },
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
 * The php://input buffer stream for the process associated with the currently active event loop.
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
 * The php://output buffer stream for the process associated with the currently active event loop.
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

/**
 * Splits the stream into chunks based on a delimiter.
 *
 * @param ReadableStream $source
 * @param string $delimiter
 *
 * @return \Traversable<string>
 */
function split(ReadableStream $source, string $delimiter): \Traversable
{
    return new AsyncGenerator(static function () use ($source, $delimiter): \Generator {
        $buffer = '';

        while (null !== $chunk = $source->read()) {
            $buffer .= $chunk;

            if (\str_contains($buffer, $delimiter)) {
                $split = \explode($delimiter, $buffer);
                $buffer = \array_pop($split);

                yield from $split;
            }
        }

        if ($buffer !== '') {
            yield $buffer;
        }
    });
}

/**
 * Splits the stream into lines.
 *
 * @param ReadableStream $source
 *
 * @return \Traversable<string>
 */
function splitLines(ReadableStream $source): \Traversable
{
    return fromIterable(split($source, "\n"))
        ->pipe(map(fn ($line) => \rtrim($line, "\r")));
}

/**
 * @return \Traversable<int, mixed> Traversable of decoded JSON
 */
function parseLineDelimitedJson(
    ReadableStream $source,
    bool $associative = false,
    int $depth = 512,
    int $options = 0
): \Traversable {
    return new AsyncGenerator(static function () use ($source, $associative, $depth, $options): \Generator {
        foreach (splitLines($source) as $line) {
            $line = \trim($line);

            if ($line === '') {
                continue;
            }

            yield \json_decode($line, $associative, $depth, $options | \JSON_THROW_ON_ERROR);
        }
    });
}
