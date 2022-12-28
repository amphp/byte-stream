<?php declare(strict_types=1);

namespace Amp\ByteStream;

use Amp\Cancellation;
use Revolt\EventLoop;

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
 * @return int The number of bytes written to the destination.
 */
function pipe(ReadableStream $source, WritableStream $destination, ?Cancellation $cancellation = null): int
{
    $written = 0;

    while (($chunk = $source->read($cancellation)) !== null) {
        $written += \strlen($chunk);
        $destination->write($chunk);
        unset($chunk); // free memory
    }

    return $written;
}

/**
 * @param int $limit Only buffer up to the given number of bytes, throwing {@see BufferException} if exceeded.
 *
 * @return string Entire contents of the InputStream.
 *
 * @throws BufferException Thrown if the maximum number of bytes is exceeded.
 */
function buffer(ReadableStream $source, ?Cancellation $cancellation = null, int $limit = \PHP_INT_MAX): string
{
    $chunks = [];
    $length = 0;

    while (null !== $chunk = $source->read($cancellation)) {
        $chunks[] = $chunk;
        $length += \strlen($chunk);
        if ($length > $limit) {
            throw new BufferException(\implode($chunks), "Buffer length limit of $limit bytes exceeded");
        }
    }

    return \implode($chunks);
}

/**
 * The php://input buffer stream for the process associated with the currently active event loop.
 */
function getInputBufferStream(): ReadableResourceStream
{
    static $map;

    $map ??= new \WeakMap();

    return $map[EventLoop::getDriver()] ??= new ReadableResourceStream(\fopen('php://input', 'rb'));
}

/**
 * The php://output buffer stream for the process associated with the currently active event loop.
 */
function getOutputBufferStream(): WritableResourceStream
{
    static $map;

    $map ??= new \WeakMap();

    return $map[EventLoop::getDriver()] ??= new WritableResourceStream(\fopen('php://output', 'wb'));
}

/**
 * The STDIN stream for the process associated with the currently active event loop.
 */
function getStdin(): ReadableResourceStream
{
    static $map;

    $map ??= new \WeakMap();

    return $map[EventLoop::getDriver()] ??= new ReadableResourceStream(\STDIN);
}

/**
 * The STDOUT stream for the process associated with the currently active event loop.
 */
function getStdout(): WritableResourceStream
{
    static $map;

    $map ??= new \WeakMap();

    return $map[EventLoop::getDriver()] ??= new WritableResourceStream(\STDOUT);
}

/**
 * The STDERR stream for the process associated with the currently active event loop.
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
 * @param non-empty-string $delimiter
 *
 * @return \Traversable<int, string>
 */
function split(ReadableStream $source, string $delimiter): \Traversable
{
    $buffer = '';

    while (null !== $chunk = $source->read()) {
        $buffer .= $chunk;

        $split = \explode($delimiter, $buffer);
        $buffer = \array_pop($split);

        yield from $split;
    }

    if ($buffer !== '') {
        yield $buffer;
    }
}

/**
 * Splits the stream into lines.
 *
 * @return \Traversable<int, string>
 */
function splitLines(ReadableStream $source): \Traversable
{
    foreach (split($source, "\n") as $line) {
        yield \rtrim($line, "\r");
    }
}

/**
 * @return \Traversable<int, mixed> Traversable of decoded JSON values
 *
 * @throws \JsonException If JSON parsing fails
 */
function parseLineDelimitedJson(
    ReadableStream $source,
    bool $associative = false,
    int $depth = 512,
    int $flags = 0
): \Traversable {
    foreach (splitLines($source) as $line) {
        $line = \trim($line);

        if ($line === '') {
            continue;
        }

        yield \json_decode($line, $associative, $depth, $flags | \JSON_THROW_ON_ERROR);
    }
}
