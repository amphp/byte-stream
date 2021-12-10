<?php

namespace Amp\ByteStream;

use Amp\Cancellation;

/**
 * ReadableStream with a single already known data chunk.
 */
final class ReadableBuffer implements ReadableStream
{
    private ?string $contents;

    /**
     * @param string|null $contents Data chunk or `null` for no data chunk.
     */
    public function __construct(?string $contents = null)
    {
        $this->contents = $contents === '' ? null : $contents;
    }

    /**
     * Reads data from the stream.
     *
     * @return string|null Returns the full contents or `null` if the stream has closed / already been consumed.
     */
    public function read(?Cancellation $cancellation = null): ?string
    {
        $contents = $this->contents;
        $this->contents = null;

        return $contents;
    }

    public function isReadable(): bool
    {
        return $this->contents !== null;
    }

    public function close(): void
    {
        $this->contents = null;
    }

    public function isClosed(): bool
    {
        return !$this->isReadable();
    }
}
