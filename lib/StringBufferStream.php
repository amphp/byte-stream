<?php

namespace Amp\ByteStream;

use Concurrent\Stream\ReadableStream;

/**
 * Input stream with a single already known data chunk.
 */
final class StringBufferStream implements ReadableStream
{
    private $contents;
    private $exception;

    /**
     * @param string|null $contents Data chunk or `null` for no data chunk.
     */
    public function __construct(string $contents = null)
    {
        $this->contents = $contents;
    }

    /** @inheritdoc */
    public function read(?int $length = null): ?string
    {
        if ($this->contents === null) {
            return null;
        }

        if ($this->exception) {
            throw $this->exception;
        }

        if ($length === null || $length >= \strlen($this->contents)) {
            $contents = $this->contents;
            $this->contents = null;
        } else {
            $contents = \substr($this->contents, 0, $length);
            $this->contents = \substr($this->contents, $length);
        }

        return $contents;
    }

    public function close(?\Throwable $e = null): void
    {
        $this->exception = $e;
    }
}
