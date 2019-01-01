<?php

namespace Amp\ByteStream;

use Concurrent\Awaitable;
use Concurrent\Stream\ReadableStream;
use Concurrent\Task;

class Payload implements ReadableStream
{
    /** @var ReadableStream */
    private $source;

    /** @var Awaitable */
    private $buffer;

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
        $this->buffer = Task::async('Amp\ByteStream\buffer', $this->source);

        return Task::await($this->buffer);
    }
}
