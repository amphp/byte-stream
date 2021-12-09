<?php

namespace Amp\ByteStream\Internal;

use Amp\ByteStream\ClosableStream;
use Amp\ByteStream\Pipe;
use Amp\ByteStream\WritableStream;

/** @internal */
final class PipeSource implements WritableStream, ClosableStream
{
    public function __construct(
        private Pipe $pipe
    ) {
    }

    public function close(): void
    {
        $this->pipe->close();
    }

    public function isClosed(): bool
    {
        return $this->pipe->isClosed();
    }

    public function write(string $bytes): void
    {
        $this->pipe->write($bytes);
    }

    public function end(): void
    {
        $this->pipe->end();
    }

    public function isWritable(): bool
    {
        return $this->pipe->isWritable();
    }
}
