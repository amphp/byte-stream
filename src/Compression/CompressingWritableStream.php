<?php

namespace Amp\ByteStream\Compression;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\WritableStream;

/**
 * Allows compression of output streams using Zlib.
 */
final class CompressingWritableStream implements WritableStream
{
    /** @var resource|null */
    private $resource;

    /**
     * @param WritableStream $destination Output stream to write the compressed data to.
     * @param int $encoding Compression encoding to use, see `deflate_init()`.
     * @param array $options Compression options to use, see `deflate_init()`.
     *
     * @throws \Error If an invalid encoding or invalid options have been passed.
     *
     * @see http://php.net/manual/en/function.deflate-init.php
     */
    public function __construct(
        private WritableStream $destination,
        private int $encoding,
        private array $options = []
    ) {
        $this->resource = @\deflate_init($encoding, $options);

        if ($this->resource === false) {
            throw new \Error("Failed initializing decompression context");
        }
    }

    public function write(string $bytes): void
    {
        if ($this->resource === null) {
            throw new ClosedException("The stream has already been closed");
        }

        $compressed = \deflate_add($this->resource, $bytes, \ZLIB_SYNC_FLUSH);

        if ($compressed === false) {
            throw new StreamException("Failed adding data to deflate context");
        }

        $this->destination->write($compressed);
    }

    public function end(): void
    {
        if ($this->resource === null) {
            throw new ClosedException("The stream has already been closed");
        }

        $compressed = \deflate_add($this->resource, '', \ZLIB_FINISH);

        if ($compressed === false) {
            throw new StreamException("Failed adding data to deflate context");
        }

        $this->resource = null;

        $this->destination->write($compressed);
        $this->destination->end();
    }

    public function isWritable(): bool
    {
        return $this->resource !== null;
    }

    /**
     * Gets the used compression encoding.
     *
     * @return int Encoding specified on construction time.
     */
    public function getEncoding(): int
    {
        return $this->encoding;
    }

    /**
     * Gets the used compression options.
     *
     * @return array Options array passed on construction time.
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
