<?php

namespace Amp\ByteStream;

use Amp\Cancellation;

/**
 * ReadableStream with a single already known data chunk.
 */
final class ReadableBuffer implements ReadableStream
{
    private ?string $contents;

    private readonly OnCloseRegistry $registry;

    /**
     * @param string|null $contents Data chunk or `null` for no data chunk.
     */
    public function __construct(?string $contents = null)
    {
        $this->contents = $contents === '' ? null : $contents;
        $this->registry = new OnCloseRegistry;

        if ($this->contents === null) {
            $this->close();
        }
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        $contents = $this->contents;
        $this->close();

        return $contents;
    }

    public function isReadable(): bool
    {
        return $this->contents !== null;
    }

    public function close(): void
    {
        $this->contents = null;
        $this->registry->call();
    }

    public function isClosed(): bool
    {
        return !$this->isReadable();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->registry->register($onClose);
    }
}
