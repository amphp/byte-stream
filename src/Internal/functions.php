<?php declare(strict_types=1);

namespace Amp\ByteStream\Internal;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;

/**
 * @internal
 * @param resource $resource Stream resource.
 */
function tryToCreateReadableStreamFromResource($resource): ReadableResourceStream
{
    return \is_resource($resource) && \get_resource_type($resource) === 'stream'
        ? new ReadableResourceStream($resource)
        : new ReadableResourceStream(\fopen('php://memory', 'rb'));
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

    $stream = new WritableResourceStream(\fopen('php://memory', 'wb'));
    $stream->close();

    return $stream;
}
