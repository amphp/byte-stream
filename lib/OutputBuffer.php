<?php

namespace Amp\ByteStream;

use Concurrent\Deferred;
use Concurrent\Task;

class OutputBuffer implements OutputStream
{
    /** @var Deferred */
    private $deferred;

    /** @var string */
    private $contents = '';

    private $closed = false;

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

        $this->deferred->resolve($this->contents);
        $this->contents = '';
    }

    public function get()
    {
        return Task::await($this->deferred->awaitable());
    }
}
