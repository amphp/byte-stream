<?php

namespace Amp\ByteStream;

use Amp\Promise;
use function Amp\call;

// @codeCoverageIgnoreStart
if (\strlen('…') !== 3) {
    throw new \Error(
        'The mbstring.func_overload ini setting is enabled. It must be disabled to use the stream package.'
    );
} // @codeCoverageIgnoreEnd

/**
 * @param \Amp\ByteStream\InputStream  $source
 * @param \Amp\ByteStream\OutputStream $destination
 *
 * @return \Amp\Promise
 */
function pipe(InputStream $source, OutputStream $destination): Promise {
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
 * Buffers an input stream into a string.
 *
 * @param InputStream $stream Any input stream.
 * @param int|null    $sizeLimit Maximum limit in bytes or `null` for no limit.
 *
 * @return Promise<string> Resolves to the buffered contents.
 */
function buffer(InputStream $stream, int $sizeLimit = null): Promise {
    return call(function () use ($stream, $sizeLimit) {
        $buffer = '';

        // We don't need to check for the size each time if there's no limit.
        if ($sizeLimit === null) {
            while (null !== $chunk = yield $stream->read()) {
                $buffer .= $chunk;
                $chunk = ''; // free memory
            }
        } else {
            while (null !== $chunk = yield $stream->read()) {
                $buffer .= $chunk;
                $chunk = ''; // free memory

                if (\strlen($buffer) > $sizeLimit) {
                    Promise\rethrow(discard($stream));

                    throw new SizeLimitException(\sprintf(
                        'The stream exceeded the specified limit of %d bytes; Read %d bytes',
                        $sizeLimit,
                        \strlen($buffer)
                    ));
                }
            }
        }

        return $buffer;
    });
}

/**
 * Discards the remaining input stream.
 *
 * Before calling this function, you must ensure there's no pending read from that input stream.
 *
 * @param InputStream $stream Any input stream.
 *
 * @return Promise Resolves once the stream has been discarded.
 *
 * @throws PendingReadError If a previous read on the stream is still pending while starting the discard process.
 */
function discard(InputStream $stream): Promise {
    return call(function () use ($stream) {
        try {
            while (null !== yield $stream->read()) {
                // Discard unread bytes from message.
            }
        } catch (\Throwable $exception) {
            // If exception is thrown here the connection closed anyway.

            // We rethrow these, because the stream can't be discarded in that case.
            if ($exception instanceof PendingReadError) {
                throw $exception;
            }
        }
    });
}
