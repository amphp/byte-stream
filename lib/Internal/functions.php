<?php

namespace Amp\ByteStream\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableStream;

/**
 * @internal
 */
function pipe(ReadableStream $source, WritableStream $destination): \Generator {
    $written = 0;

    while (yield $source->advance()) {
        $data = $source->getChunk();
        $written += \strlen($data);
        yield $destination->write($data);
    }

    return $written;
}
