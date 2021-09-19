<?php

namespace Amp\ByteStream;

use Amp\Deferred;
use Amp\Future;

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

    public function write(string $data): Future
    {
        if ($this->closed) {
            return Future::error(new ClosedException("The stream has already been closed"));
        }

        $this->contents .= $data;
        return Future::complete(null);
    }

    public function end(string $finalData = ""): Future
    {
        if ($this->closed) {
            return Future::error(new ClosedException("The stream has already been closed"));
        }

        $this->contents .= $finalData;
        $this->closed = true;

        $this->deferred->complete($this->contents);
        $this->contents = '';
        return Future::complete(null);
    }

    public function buffer(): string
    {
        return $this->deferred->getFuture()->await();
    }
}
