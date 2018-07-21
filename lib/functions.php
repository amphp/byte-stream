<?php

namespace Amp\ByteStream;

// @codeCoverageIgnoreStart
if (\strlen('…') !== 3) {
    throw new \Error(
        'The mbstring.func_overload ini setting is enabled. It must be disabled to use the stream package.'
    );
} // @codeCoverageIgnoreEnd

if (!\defined('STDOUT')) {
    \define('STDOUT', \fopen('php://stdout', 'wb'));
}

if (!\defined('STDERR')) {
    \define('STDERR', \fopen('php://stderr', 'wb'));
}

/**
 * @param InputStream  $source
 * @param OutputStream $destination
 *
 * @return int
 *
 * @throws StreamException
 */
function pipe(InputStream $source, OutputStream $destination): int
{
    $written = 0;

    while (null !== $chunk = $source->read()) {
        $written += \strlen($chunk);
        $destination->write($chunk);
    }

    return $written;
}

/**
 * @param InputStream $source
 *
 * @return string
 *
 * @throws StreamException
 */
function buffer(InputStream $source): string
{
    $buffer = "";

    while (null !== $chunk = $source->read()) {
        $buffer .= $chunk;
    }

    return $buffer;
}
