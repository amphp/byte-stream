<?php

namespace Amp\ByteStream;

use Amp\Future;

/**
 * Allows compression of output streams using Zlib.
 */
final class ZlibWritableStream implements WritableStream
{
    /** @var resource|null */
    private $resource;

    /**
     * @param WritableStream $destination Output stream to write the compressed data to.
     * @param int          $encoding Compression encoding to use, see `deflate_init()`.
     * @param array        $options Compression options to use, see `deflate_init()`.
     *
     * @throws StreamException If an invalid encoding or invalid options have been passed.
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
            throw new StreamException("Failed initializing deflate context");
        }
    }

    /** @inheritdoc */
    public function write(string $data): Future
    {
        if ($this->resource === null) {
            return Future::error(new ClosedException("The stream has already been closed"));
        }

        \assert($this->destination !== null);

        $compressed = \deflate_add($this->resource, $data, \ZLIB_SYNC_FLUSH);

        if ($compressed === false) {
            return Future::error(new StreamException("Failed adding data to deflate context"));
        }

        return $this->destination->write($compressed);
    }

    /** @inheritdoc */
    public function end(string $finalData = ""): Future
    {
        if ($this->resource === null) {
            return Future::error(new ClosedException("The stream has already been closed"));
        }

        \assert($this->destination !== null);

        $compressed = \deflate_add($this->resource, $finalData, \ZLIB_FINISH);

        if ($compressed === false) {
            return Future::error(new StreamException("Failed adding data to deflate context"));
        }

        $this->resource = null;

        return $this->destination->end($compressed);
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
