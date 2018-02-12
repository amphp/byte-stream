<?php

namespace Amp\ByteStream;

/**
 * Input stream with a single already known data chunk.
 */
final class InMemoryStream implements InputStream {
    private $contents;

    /**
     * @param string|null $contents Data chunk or `null` for no data chunk.
     */
    public function __construct(string $contents = null) {
        $this->contents = $contents;
    }

    /** @inheritdoc */
    public function read(): ?string {
        if ($this->contents === null) {
            return null;
        }

        $contents = $this->contents;
        $this->contents = null;

        return $contents;
    }
}
