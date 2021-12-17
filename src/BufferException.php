<?php

namespace Amp\ByteStream;

final class BufferException extends StreamException
{
    private string $buffer;

    public function __construct(string $buffer, string $message, int $code = 0, ?\Throwable $previous = null)
    {
        $this->buffer = $buffer;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string The buffered string when the buffer limit was exceeded. Note that the length of this string
     * may exceed the set limit.
     */
    public function getBuffer(): string
    {
        return $this->buffer;
    }
}
