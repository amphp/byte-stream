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
 * @param InputStream  $source
 * @param OutputStream $destination
 *
 * @return int The number of bytes written to the destination.
 */
function pipe(InputStream $source, OutputStream $destination, ?Cancellation $cancellation = null): int
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
 * @return array{InputStream, OutputStream}
 */
function createStreamPair(): array
{
    $emitter = new Emitter();

    return [
        new PipelineStream($emitter->asPipeline()),
        new class ($emitter) implements OutputStream {
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
 * @param InputStream $source
 *
 * @return string Entire contents of the InputStream.
 */
function buffer(InputStream $source): string
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
 * @return ResourceInputStream
 */
function getInputBufferStream(): ResourceInputStream
{
    static $map;
    $map ??= new \WeakMap();
    return $map[EventLoop::getDriver()] ??= new ResourceInputStream(\fopen('php://input', 'rb'));
}

/**
 * The php://output output buffer stream for the process associated with the currently active event loop.
 *
 * @return ResourceOutputStream
 */
function getOutputBufferStream(): ResourceOutputStream
{
    static $map;
    $map ??= new \WeakMap();
    return $map[EventLoop::getDriver()] ??= new ResourceOutputStream(\fopen('php://output', 'wb'));
}

/**
 * The STDIN stream for the process associated with the currently active event loop.
 *
 * @return ResourceInputStream
 */
function getStdin(): ResourceInputStream
{
    static $map;
    $map ??= new \WeakMap();
    return $map[EventLoop::getDriver()] ??= new ResourceInputStream(\STDIN);
}

/**
 * The STDOUT stream for the process associated with the currently active event loop.
 *
 * @return ResourceOutputStream
 */
function getStdout(): ResourceOutputStream
{
    static $map;
    $map ??= new \WeakMap();
    return $map[EventLoop::getDriver()] ??= new ResourceOutputStream(\STDOUT);
}

/**
 * The STDERR stream for the process associated with the currently active event loop.
 *
 * @return ResourceOutputStream
 */
function getStderr(): ResourceOutputStream
{
    static $map;
    $map ??= new \WeakMap();
    return $map[EventLoop::getDriver()] ??= new ResourceOutputStream(\STDERR);
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
