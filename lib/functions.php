<?php

namespace Amp\ByteStream;

use Amp\Loop;
use Amp\Promise;
use function Amp\call;

// @codeCoverageIgnoreStart
if (\strlen('…') !== 3) {
    throw new \Error(
        'The mbstring.func_overload ini setting is enabled. It must be disabled to use the stream package.'
    );
} // @codeCoverageIgnoreEnd

if (!\defined('STDOUT')) {
    \define('STDOUT', \fopen('php://stdout', 'w'));
}

if (!\defined('STDERR')) {
    \define('STDERR', \fopen('php://stderr', 'w'));
}

/**
 * @param \Amp\ByteStream\InputStream  $source
 * @param \Amp\ByteStream\OutputStream $destination
 *
 * @return \Amp\Promise
 */
function pipe(InputStream $source, OutputStream $destination): Promise
{
    return call(function () use ($source, $destination): \Generator {
        $written = 0;

        while (($chunk = yield $source->read()) !== null) {
            $written += \strlen($chunk);
            $writePromise = $destination->write($chunk);
            $chunk = null; // free memory
            yield $writePromise;
        }

        return $written;
    });
}

/**
 * @param \Amp\ByteStream\InputStream  $source
 *
 * @return \Amp\Promise
 */
function buffer(InputStream $source): Promise
{
    return call(function () use ($source): \Generator {
        $buffer = "";

        while (($chunk = yield $source->read()) !== null) {
            $buffer .= $chunk;
            $chunk = null; // free memory
        }

        return $buffer;
    });
}

/**
 * The php://input output buffer stream for the process associated with the currently active event loop.
 *
 * @return ResourceInputStream
 */
function getInput(): ResourceInputStream
{
    static $key = InputStream::class . '\\input';

    $stream = Loop::getState($key);

    if (!$stream) {
        $stream = new ResourceInputStream(\fopen('php://input', 'r'));
        Loop::setState($key, $stream);
    }

    return $stream;
}

/**
 * The php://output stream for the process associated with the currently active event loop.
 *
 * @return ResourceOutputStream
 */
function getOutput(): ResourceOutputStream
{
    static $key = OutputStream::class . '\\output';

    $stream = Loop::getState($key);

    if (!$stream) {
        $stream = new ResourceOutputStream(\fopen('php://output', 'w'));
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
