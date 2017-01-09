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
function pipe(ByteStream $source, ByteStream $destination, int $bytes = null): Promise {
    return new Coroutine(Internal\pipe($source, $destination, $bytes));
}
