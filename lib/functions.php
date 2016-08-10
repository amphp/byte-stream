<?php

namespace Amp\Stream;

use Amp\Coroutine;

// @codeCoverageIgnoreStart
if (\strlen('…') !== 3) {
    throw new \RuntimeException(
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
function pipe(Stream $source, Stream $destination, $bytes = null) {
    return new Coroutine(__doPipe($source, $destination, $bytes));
}

function __doPipe(Stream $source, Stream $destination, $bytes = null) {
    if (!$destination->isWritable()) {
        throw new \LogicException("The destination is not writable");
    }
    
    if (null !== $bytes) {
        yield Coroutine::result(
            yield $destination->write(
                yield $source->read($bytes)
            )
        );
        return;
    }
    
    $written = 0;
    
    do {
        $written += (yield $destination->write(
            yield $source->read()
        ));
    } while ($source->isReadable() && $destination->isWritable());
    
    yield Coroutine::result($written);
}
