<?php

namespace Amp\ByteStream;

use Amp\CancellationToken;

/**
 * Allows decompression of input streams using Zlib.
 */
final class ZlibInputStream implements InputStream
{
    /** @var resource|null */
    private $resource;

    /**
     * @param InputStream $source Input stream to read compressed data from.
     * @param int         $encoding Compression algorithm used, see `inflate_init()`.
     * @param array       $options Algorithm options, see `inflate_init()`.
     *
     * @throws StreamException
     * @throws \Error
     *
     * @see http://php.net/manual/en/function.inflate-init.php
     */
    public function __construct(
        private InputStream $source,
        private int $encoding,
        private array $options = [],
    ) {
        $this->resource = @\inflate_init($encoding, $options);

        if ($this->resource === false) {
            throw new StreamException("Failed initializing deflate context");
        }
    }

    /** @inheritdoc */
    public function read(?CancellationToken $token = null): ?string
    {
        if ($this->resource === null) {
            return null;
        }

        $data = $this->source->read($token);

        // Needs a double guard, as stream might have been closed while reading
        /** @psalm-suppress ParadoxicalCondition */
        if ($this->resource === null) {
            return null;
        }

        if ($data === null) {
            $decompressed = @\inflate_add($this->resource, "", \ZLIB_FINISH);

            if ($decompressed === false) {
                throw new StreamException("Failed adding data to deflate context");
            }

            $this->close();

            return $decompressed;
        }

        $decompressed = @\inflate_add($this->resource, $data, \ZLIB_SYNC_FLUSH);

        if ($decompressed === false) {
            throw new StreamException("Failed adding data to deflate context");
        }

        return $decompressed;
    }

    public function isReadable(): bool
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

    /**
     * @return void
     * @internal
     */
    private function close(): void
    {
        $this->resource = null;
    }
}
