<?php

namespace Amp\ByteStream;

/**
 * Allows compression of output streams using Zlib.
 */
final class ZlibOutputStream implements OutputStream
{
    private $destination;
    private $encoding;
    private $options;
    private $resource;

    /**
     * @param OutputStream $destination Output stream to write the compressed data to.
     * @param int          $encoding Compression encoding to use, see `deflate_init()`.
     * @param array        $options Compression options to use, see `deflate_init()`.
     *
     * @throws StreamException If an invalid encoding or invalid options have been passed.
     *
     * @see http://php.net/manual/en/function.deflate-init.php
     */
    public function __construct(OutputStream $destination, int $encoding, array $options = [])
    {
        $this->destination = $destination;
        $this->encoding = $encoding;
        $this->options = $options;
        $this->resource = @\deflate_init($encoding, $options);

        if ($this->resource === false) {
            throw new StreamException("Failed initializing deflate context");
        }
    }

    /** @inheritdoc */
    public function write(string $data): void
    {
        if ($this->resource === null) {
            throw new ClosedException("The stream has already been closed");
        }

        $compressed = \deflate_add($this->resource, $data, \ZLIB_SYNC_FLUSH);

        if ($compressed === false) {
            throw new StreamException("Failed adding data to deflate context");
        }

        try {
            $this->destination->write($compressed);
        } catch (\Throwable $e) {
            $this->close();

            if ($e instanceof StreamException) {
                throw $e;
            }

            throw new StreamException("Writing the the stream failed unexpectedly", 0, $e);
        }
    }

    /** @inheritdoc */
    public function end(string $finalData = ""): void
    {
        if ($this->resource === null) {
            throw new ClosedException("The stream has already been closed");
        }

        $compressed = \deflate_add($this->resource, $finalData, \ZLIB_FINISH);

        if ($compressed === false) {
            throw new StreamException("Failed adding data to deflate context");
        }

        try {
            $this->destination->end($finalData);
        } catch (\Throwable $e) {
            if ($e instanceof StreamException) {
                throw $e;
            }

            throw new StreamException("Writing the the stream failed unexpectedly", 0, $e);
        } finally {
            $this->close();
        }
    }

    /** @internal */
    private function close()
    {
        $this->resource = null;
        $this->destination = null;
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
