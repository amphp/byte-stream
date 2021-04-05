<?php

namespace Amp\ByteStream;

use Revolt\Future\Deferred;
use Revolt\Future\Future;

class OutputBuffer implements OutputStream
{
    /** @var Deferred */
    private Deferred $deferred;

    /** @var string */
    private string $contents = '';

    /** @var bool */
    private bool $closed = false;

    public function __construct()
    {
        $this->deferred = new Deferred;
    }

    public function write(string $data): void
    {
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed.");
        }

        $this->contents .= $data;
    }

    public function end(string $finalData = ""): void
    {
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed.");
        }

        $this->contents .= $finalData;
        $this->closed = true;

        $this->deferred->complete($this->contents);
        $this->contents = '';
    }

    public function buffer(): Future
    {
        return $this->deferred->getFuture();
    }
}
