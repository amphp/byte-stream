<?php

namespace Amp\ByteStream;

final class CannotWriteChunkException extends StreamException
{
    /**
     * @var int
     */
    private $positionInChunk;

    public function __construct(int $positionInChunk, string $message = "", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->positionInChunk = $positionInChunk;
    }

    /**
     * Returns position in input chunk where write failure occured.
     * Rest of data in chunk after this position failed to be written.
     */
    public function getFailingPositionInChunk(): int
    {
        return $this->positionInChunk;
    }
}
