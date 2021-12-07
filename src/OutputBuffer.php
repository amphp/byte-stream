<?php

namespace Amp\ByteStream;

use Amp\DeferredFuture;
use Amp\Future;

class OutputBuffer implements WritableStream
{
    private DeferredFuture $deferredFuture;

    private string $contents = '';

    private bool $closed = false;

    public function __construct()
    {
        $this->deferredFuture = new DeferredFuture;
    }

    public function write(string $bytes): Future
    {
        if ($this->closed) {
            return Future::error(new ClosedException("The stream has already been closed"));
        }

        $this->contents .= $bytes;
        return Future::complete();
    }

    public function end(string $bytes = ""): Future
    {
        if ($this->closed) {
            return Future::error(new ClosedException("The stream has already been closed"));
        }

        $this->contents .= $bytes;
        $this->closed = true;

        $this->deferredFuture->complete($this->contents);
        $this->contents = '';
        return Future::complete(null);
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
