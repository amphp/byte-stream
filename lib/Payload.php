<?php

namespace Amp\ByteStream;

use Concurrent\Stream\ReadableStream;

class Payload implements ReadableStream
{
    private $source;

    public function __construct(ReadableStream $source)
    {
        $this->source = $source;
    }

    /** @inheritdoc */
    final public function close(?\Throwable $e = null): void
    {
        $this->source->close($e);
    }

    /** @inheritdoc */
    final public function read(?int $length = null): ?string
    {
        return $this->source->read();
    }

    /**
     * @return string
     *
     * @throws \Concurrent\Stream\PendingReadException
     * @throws \Concurrent\Stream\StreamClosedException
     */
    final public function buffer(): string
    {
        return buffer($this->source);
    }
}
