<?php

namespace Amp\ByteStream\Internal;

use Amp\ByteStream\{ ReadableStream, WritableStream };

/**
 * @internal
 */
function pipe(ReadableStream $source, WritableStream $destination): \Generator {
    $written = 0;

    while (yield $source->advance()) {
        $written += yield $destination->write($source->getChunk());
    }

    return $written;
}
