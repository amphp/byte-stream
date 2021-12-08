<?php

namespace Amp\ByteStream;

use Amp\DeferredFuture;

final class WriteBuffer implements WritableStream
{
    private DeferredFuture $deferredFuture;

    private string $contents = '';

    private bool $closed = false;

    public function __construct()
    {
        $this->deferredFuture = new DeferredFuture;
    }

    public function write(string $bytes): void
    {
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed");
        }

        $this->contents .= $bytes;
    }

    public function end(string $bytes = ""): void
    {
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed");
        }

        $this->contents .= $bytes;
        $this->closed = true;

        $this->deferredFuture->complete($this->contents);
        $this->contents = '';
    }

    public function isWritable(): bool
    {
        return !$this->closed;
    }

    public function buffer(): string
    {
        return $this->deferredFuture->getFuture()->await();
    }
}
