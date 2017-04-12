<?php

namespace Amp\ByteStream;

use Amp\{ Coroutine, Promise };

// @codeCoverageIgnoreStart
if (\strlen('…') !== 3) {
    throw new \Error(
        'The mbstring.func_overload ini setting is enabled. It must be disable to use the stream package.'
    );
} // @codeCoverageIgnoreEnd

/**
 * @param \Amp\ByteStream\ReadableStream $source
 * @param \Amp\ByteStream\WritableStream $destination
 *
 * @return \Amp\Promise
 */
function pipe(ReadableStream $source, WritableStream $destination): Promise {
    return new Coroutine(Internal\pipe($source, $destination));
}
