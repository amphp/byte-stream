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
