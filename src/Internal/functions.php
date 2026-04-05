<?php declare(strict_types=1);

namespace Amp\ByteStream\Internal;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\WritableResourceStream;

/**
 * @internal
 * @param resource $resource Stream resource.
 */
function tryToCreateReadableStreamFromResource($resource): ReadableResourceStream
{
    if (\is_resource($resource) && \get_resource_type($resource) === 'stream') {
        return new ReadableResourceStream($resource);
    }

    $resource = \fopen('php://memory', 'rb');
    if ($resource === false) {
        throw new StreamException('Failed to open php://memory for reading');
    }

    $stream = new ReadableResourceStream($resource);
    $stream->close();

    return $stream;
}

/**
 * @internal
 * @param resource $resource Stream resource.
 */
function tryToCreateWritableStreamFromResource($resource): WritableResourceStream
{
    if (\is_resource($resource) && \get_resource_type($resource) === 'stream') {
        return new WritableResourceStream($resource);
    }

    $resource = \fopen('php://memory', 'wb');
    if ($resource === false) {
        throw new StreamException('Failed to open php://memory for writing');
    }

    $stream = new WritableResourceStream($resource);
    $stream->close();

    return $stream;
}
