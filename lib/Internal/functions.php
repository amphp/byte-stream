<?php

namespace Amp\ByteStream\Internal;

use Amp\ByteStream\{ ReadableStream, StreamException, WritableStream };

/**
 * @internal
 */
function pipe(ReadableStream $source, WritableStream $destination, int $bytes = null, int $chunkSize = 8192): \Generator {
    if (!$destination->isWritable()) {
        throw new StreamException("The destination is not writable");
    }

    $written = 0;

    if ($bytes !== null) {
        for ($remaining = $bytes; $remaining > 0; $remaining -= $chunkSize) {
            $written += yield $destination->write(
                yield $source->read($remaining > $chunkSize ? $chunkSize : $remaining)
            );
        }
    } else {
        do {
            $written += yield $destination->write(
                yield $source->read()
            );
        } while ($source->isReadable());
    }

    return $written;
}
