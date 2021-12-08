<?php

namespace Amp\ByteStream\Internal;

use Amp\ByteStream\ClosableStream;
use Amp\ByteStream\Pipe;
use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;

/** @internal */
final class PipeSink implements ReadableStream, ClosableStream
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

    public function read(?Cancellation $cancellation = null): ?string
    {
        return $this->pipe->read($cancellation);
    }

    public function isReadable(): bool
    {
        return $this->pipe->isReadable();
    }
}
