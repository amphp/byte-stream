<?php

namespace Amp\ByteStream;

use Amp\DeferredFuture;
use Amp\Future;

class OutputBuffer implements OutputStream
{
    private DeferredFuture $deferredFuture;

    private string $contents = '';

    private bool $closed = false;

    public function __construct()
    {
        $this->deferredFuture = new DeferredFuture;
    }

    public function write(string $data): Future
    {
        if ($this->closed) {
            return Future::error(new ClosedException("The stream has already been closed"));
        }

        $this->contents .= $data;
        return Future::complete();
    }

    public function end(string $finalData = ""): Future
    {
        if ($this->closed) {
            return Future::error(new ClosedException("The stream has already been closed"));
        }

        $this->contents .= $finalData;
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
