<?php

namespace Amp\ByteStream;

use Amp\Iterator;
use Amp\Loop;
use Amp\Producer;
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
 * @param \Amp\ByteStream\InputStream $source
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
 * Get LineReader instance associated with STDIN.
 *
 * @return LineReader
 */
function getStdinLineReader(): LineReader
{
    static $key = LineReader::class . '\\stdin';

    $stream = Loop::getState($key);

    if (!$stream) {
        $stream = new LineReader(getStdin());
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

/**
 * Buffered async version of the readline() function.
 *
 * Please note that this function will hungrily eat data from stdin,
 * buffering data even after the first newline.
 * Use getStdinLineReader()->getBuffer() to obtain the remaining buffered data.
 *
 * @param string $prompt Optional prompt to print to console
 *
 * @return \Amp\Promise Will resolve with the read line
 */
function prompt(string $prompt = ''): Promise
{
    return call(static function () use ($prompt) {
        if ($prompt) {
            yield getStdout()->write($prompt);
        }
        return getStdinLineReader()->readLine();
    });
}

/**
 * Simple wrapper function to asynchronously write a string to the PHP output buffer.
 *
 * @param string $string
 * @return Promise
 */
function bufferEcho($string): Promise
{
    return getOutputBufferStream()->write($string);
}
function parseLineDelimitedJson(InputStream $stream, bool $assoc = false, int $depth = 512, int $options = 0): Iterator
{
    return new Producer(static function (callable $emit) use ($stream, $assoc, $depth, $options) {
        $reader = new LineReader($stream);

        while (null !== $line = yield $reader->readLine()) {
            $line = \trim($line);

            if ($line === '') {
                continue;
            }

            /** @noinspection PhpComposerExtensionStubsInspection */
            $data = \json_decode($line, $assoc, $depth, $options);
            /** @noinspection PhpComposerExtensionStubsInspection */
            $error = \json_last_error();

            /** @noinspection PhpComposerExtensionStubsInspection */
            if ($error !== \JSON_ERROR_NONE) {
                /** @noinspection PhpComposerExtensionStubsInspection */
                throw new StreamException('Failed to parse JSON: ' . \json_last_error_msg(), $error);
            }

            yield $emit($data);
        }
    });
}
