<?php

namespace Amp\ByteStream;

use Amp\CancellationToken;

/**
 * Input stream with a single already known data chunk.
 */
final class InMemoryStream implements InputStream
{
    private ?string $contents;

    /**
     * @param string|null $contents Data chunk or `null` for no data chunk.
     */
    public function __construct(string $contents = null)
    {
        $this->contents = $contents;
    }

    /**
     * Reads data from the stream.
     *
     * @return string|null Returns the full contents or `null` if the stream has closed / already been consumed.
     */
    public function read(?CancellationToken $token = null): ?string
    {
        if ($this->contents === null) {
            return null;
        }

        $contents = $this->contents;
        $this->contents = null;

        return $contents;
    }

    public function isReadable(): bool
    {
        return $this->contents !== null;
    }
}
