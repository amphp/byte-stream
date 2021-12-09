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

    public function __construct()
    {
        $emitter = new Emitter();

        $this->sink = new EmitterStream($emitter);
        $this->source = new IterableStream($emitter->pipe());
    }

    /**
     * @return ReadableStream&ClosableStream
     */
    public function getSource(): ReadableStream /* & ClosableStream */
    {
        return $this->source;
    }

    /**
     * @return WritableStream&ClosableStream
     */
    public function getSink(): WritableStream /* & ClosableStream */
    {
        return $this->sink;
    }
}
