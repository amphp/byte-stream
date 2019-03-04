<?php

namespace Amp\ByteStream;

final class CannotWriteChunkException extends StreamException
{
    /**
     * @var string
     */
    private $chunk;

    public function __construct(string $chunk, string $message = "", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->chunk = $chunk;
    }

    public function getChunk(): string
    {
        return $this->chunk;
    }
}
