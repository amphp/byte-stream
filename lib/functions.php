<?php

namespace Amp\Stream;

use Amp\Coroutine;
use AsyncInterop\Promise;

// @codeCoverageIgnoreStart
if (\strlen('…') !== 3) {
    throw new \Error(
        'The mbstring.func_overload ini setting is enabled. It must be disable to use the stream package.'
    );
} // @codeCoverageIgnoreEnd

/**
 * @param \Amp\Stream\ByteStream $source
 * @param \Amp\Stream\ByteStream $destination
 * @param int|null $bytes
 *
 * @return \AsyncInterop\Promise
 */
    return new Coroutine(__doPipe($source, $destination, $bytes));
}

function __doPipe(Stream $source, Stream $destination, int $bytes = null): \Generator {
    if (!$destination->isWritable()) {
        throw new \LogicException("The destination is not writable");
    }
    
    if (null !== $bytes) {
        return yield $destination->write(
            yield $source->read($bytes)
        );
    }
    
    $written = 0;
    
    do {
        $written += yield $destination->write(
            yield $source->read()
        );
    } while ($source->isReadable() && $destination->isWritable());
    
    return $written;
function pipe(ByteStream $source, ByteStream $destination, int $bytes = null): Promise {
}
