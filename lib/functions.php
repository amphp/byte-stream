<?php declare(strict_types = 1);

namespace Amp\Stream;

use Amp\Coroutine;
use Interop\Async\Awaitable;

// @codeCoverageIgnoreStart
if (\strlen('…') !== 3) {
    throw new \Error(
        'The mbstring.func_overload ini setting is enabled. It must be disable to use the stream package.'
    );
} // @codeCoverageIgnoreEnd

/**
 * @param \Amp\Stream\Stream $source
 * @param \Amp\Stream\Stream $destination
 * @param int|null $bytes
 *
 * @return \Interop\Async\Awaitable
 */
function pipe(Stream $source, Stream $destination, int $bytes = null): Awaitable {
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
}
