<?php

namespace Amp\ByteStream;

// @codeCoverageIgnoreStart
use Concurrent\Stream\PendingReadException;
use Concurrent\Stream\ReadableStream;
use Concurrent\Stream\StreamClosedException;
use Concurrent\Stream\WritableStream;

if (\strlen('…') !== 3) {
    throw new \Error(
        'The mbstring.func_overload ini setting is enabled. It must be disabled to use the stream package.'
    );
}

if (!\defined('STDOUT')) {
    \define('STDOUT', \fopen('php://stdout', 'wb'));
}

if (!\defined('STDERR')) {
    \define('STDERR', \fopen('php://stderr', 'wb'));
} // @codeCoverageIgnoreEnd

/**
 * Pipes a readable stream into a writable stream.
 *
 * @param ReadableStream $source Stream to read from.
 * @param WritableStream $destination Stream to write to.
 * @param int            $chunkSize Chunk size to use for reading.
 *
 * @return int Bytes read from the readable stream.
 *
 * @throws StreamClosedException
 * @throws PendingReadException
 */
function pipe(ReadableStream $source, WritableStream $destination, int $chunkSize = 8192): int
{
    $written = 0;

    while (null !== $chunk = $source->read($chunkSize)) {
        $written += \strlen($chunk);
        $destination->write($chunk);
    }

    return $written;
}

/**
 * Reads all bytes from a readable stream and buffers them into a string.
 *
 * @param ReadableStream $source Stream to read from.
 * @param int            $chunkSize Chunk size to use for reading.
 *
 * @return string Buffered contents.
 *
 * @throws StreamClosedException
 * @throws PendingReadException
 */
function buffer(ReadableStream $source, int $chunkSize = 8192): string
{
    $buffer = "";

    while (null !== $chunk = $source->read($chunkSize)) {
        $buffer .= $chunk;
        unset($chunk); // free memory
    }

    return $buffer;
}
