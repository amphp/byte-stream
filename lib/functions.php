<?php

namespace Amp\ByteStream;

use Amp\AsyncGenerator;
use Amp\Pipeline;
use Amp\Promise;
use Revolt\EventLoop\Loop;
use function Amp\async;

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
 * @param InputStream  $source
 * @param OutputStream $destination
 *
 * @return Promise<int> Resolves with the number of bytes written to the destination.
 */
function pipe(InputStream $source, OutputStream $destination): Promise
{
    return async(function () use ($source, $destination): int {
        $written = 0;

        while (($chunk = $source->read()) !== null) {
            $written += \strlen($chunk);
            $destination->write($chunk);
            $chunk = null; // free memory
        }

        return $written;
    });
}

/**
 * @param InputStream $source
 *
 * @return string Entire contents of the InputStream.
 */
function buffer(InputStream $source): string
{
    $buffer = "";

    while (($chunk = $source->read()) !== null) {
        $buffer .= $chunk;
        $chunk = null; // free memory
    }

    return $buffer;
}

/**
 * The php://input input buffer stream for the process associated with the currently active event loop.
 *
 * @return ResourceInputStream
 */
function getInputBufferStream(): ResourceInputStream
{
    static $key = InputStream::class . '\\input';

    $stream = Loop::getState($key);

    if (!$stream) {
        $stream = new ResourceInputStream(\fopen('php://input', 'rb'));
        Loop::setState($key, $stream);
    }

    return $stream;
}

/**
 * The php://output output buffer stream for the process associated with the currently active event loop.
 *
 * @return ResourceOutputStream
 */
function getOutputBufferStream(): ResourceOutputStream
{
    static $key = OutputStream::class . '\\output';

    $stream = Loop::getState($key);

    if (!$stream) {
        $stream = new ResourceOutputStream(\fopen('php://output', 'wb'));
        Loop::setState($key, $stream);
    }

    return $stream;
}

/**
 * The STDIN stream for the process associated with the currently active event loop.
 *
 * @return ResourceInputStream
 */
function getStdin(): ResourceInputStream
{
    static $key = InputStream::class . '\\stdin';

    $stream = Loop::getState($key);

    if (!$stream) {
        $stream = new ResourceInputStream(\STDIN);
        Loop::setState($key, $stream);
    }

    return $stream;
}

/**
 * The STDOUT stream for the process associated with the currently active event loop.
 *
 * @return ResourceOutputStream
 */
function getStdout(): ResourceOutputStream
{
    static $key = OutputStream::class . '\\stdout';

    $stream = Loop::getState($key);

    if (!$stream) {
        $stream = new ResourceOutputStream(\STDOUT);
        Loop::setState($key, $stream);
    }

    return $stream;
}

/**
 * The STDERR stream for the process associated with the currently active event loop.
 *
 * @return ResourceOutputStream
 */
function getStderr(): ResourceOutputStream
{
    static $key = OutputStream::class . '\\stderr';

    $stream = Loop::getState($key);

    if (!$stream) {
        $stream = new ResourceOutputStream(\STDERR);
        Loop::setState($key, $stream);
    }

    return $stream;
}

function parseLineDelimitedJson(InputStream $stream, bool $assoc = false, int $depth = 512, int $options = 0): Pipeline
{
    return new AsyncGenerator(static function () use ($stream, $assoc, $depth, $options): \Generator {
        $reader = new LineReader($stream);

        while (null !== $line = $reader->readLine()) {
            $line = \trim($line);

            if ($line === '') {
                continue;
            }

            $data = \json_decode($line, $assoc, $depth, $options);
            $error = \json_last_error();

            if ($error !== \JSON_ERROR_NONE) {
                throw new StreamException('Failed to parse JSON: ' . \json_last_error_msg(), $error);
            }

            yield $data;
        }
    });
}
