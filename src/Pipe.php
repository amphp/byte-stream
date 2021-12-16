<?php

namespace Amp\ByteStream;

use Amp\Pipeline\Emitter;

/**
 * Create a local stream where data written to the pipe is immediately available on the pipe.
 *
 * Primarily useful for testing.
 */
final class Pipe
{
    private WritableStream $sink;

    private ReadableStream $source;

    public function __construct(int $bufferSize)
    {
        $emitter = new Emitter();

        $this->sink = new EmitterStream($emitter, $bufferSize);
        $this->source = new IterableStream($emitter->pipe());
    }

    /**
     * @return ReadableStream Data written to the WritableStream returned by {@see getSink()} will be readable
     * on this stream.
     */
    public function getSource(): ReadableStream
    {
        return $this->source;
    }

    /**
     * @return WritableStream Data written to this stream will be readable by the stream returned from
     * {@see getSource()}.
     */
    public function getSink(): WritableStream
    {
        return $this->sink;
    }
}
