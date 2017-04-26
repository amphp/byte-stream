<?php

namespace Amp\ByteStream;

use Amp\Coroutine;
use Amp\Promise;
use function Amp\call;

// @codeCoverageIgnoreStart
if (\strlen('…') !== 3) {
    throw new \Error(
        'The mbstring.func_overload ini setting is enabled. It must be disabled to use the stream package.'
    );
} // @codeCoverageIgnoreEnd

/**
 * @param ReadableStream $source
 * @param WritableStream $destination
 *
 * @return Promise
 */
function pipe(ReadableStream $source, WritableStream $destination): Promise {
    return new Coroutine(Internal\pipe($source, $destination));
}

/**
 * Reads a complete `ReadableStream` into a string. If `$maxLength` is given and the length exceeds the maximum
 * length, the promise is failed with a `SizeExceededException`.
 *
 * @param ReadableStream $source Stream to read.
 * @param int|null       $maxLength Maximum length to accept, `null` for unlimited.
 *
 * @return Promise
 */
function readAll(ReadableStream $source, int $maxLength = null): Promise {
    return call(function () use ($source, $maxLength) {
        if ($maxLength === null) {
            $maxLength = \INF;
        }

        $buffer = "";

        while (yield $source->advance()) {
            $buffer .= $source->getChunk();

            if (\strlen($buffer) > $maxLength) {
                unset($buffer);

                while (yield $source->advance()) ;

                throw new SizeExceededException("The maximum size of {$maxLength} has been exceeded");
            }
        }

        return $buffer;
    });
}
