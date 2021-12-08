<?php

namespace Amp\ByteStream;

use Amp\ByteStream\Internal\PipeSink;
use Amp\ByteStream\Internal\PipeSource;
use Amp\Cancellation;
use Amp\Future;
use Amp\Pipeline\Emitter;

/**
 * Create a local stream where data written to the pipe is immediately available on the pipe.
 *
 * Primarily useful for testing.
 */
final class Pipe implements ReadableStream, WritableStream, ClosableStream
{
    private Emitter $emitter;
    private ReadableStream $stream;

    public function __construct()
    {
        $this->emitter = new Emitter();
        $this->stream = new IterableStream($this->emitter->pipe());
    }

    public function getSource(): WritableStream /* & ClosableStream */
    {
        return new PipeSource($this);
    }

    public function getSink(): ReadableStream /* & ClosableStream */
    {
        return new PipeSink($this);
    }

    public function write(string $bytes): Future
    {
        if ($this->emitter->isComplete()) {
            return Future::error(new ClosedException('The stream is no longer writable'));
        }

        return $this->emitter->emit($bytes);
    }

    public function end(string $bytes = ""): Future
    {
        $future = $this->write($bytes);

        if (!$this->emitter->isComplete()) {
            $this->emitter->complete();
        }

        return $future;
    }

    public function isWritable(): bool
    {
        return !$this->emitter->isComplete();
    }

    public function close(): void
    {
        if (!$this->emitter->isComplete()) {
            $this->emitter->complete();
        }
    }

    public function isClosed(): bool
    {
        return !$this->isWritable() && !$this->isReadable();
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        return $this->stream->read($cancellation);
    }

    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }
}
