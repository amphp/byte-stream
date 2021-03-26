<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\OutputStream;
use Amp\ByteStream\StreamException;

final class Base64DecodingOutputStream implements OutputStream
{
    /** @var OutputStream */
    private OutputStream $destination;

    /** @var string */
    private string $buffer = '';

    /** @var int */
    private int $offset = 0;

    public function __construct(OutputStream $destination)
    {
        $this->destination = $destination;
    }

    public function write(string $data): void
    {
        $this->buffer .= $data;

        $length = \strlen($this->buffer);
        $chunk = \base64_decode(\substr($this->buffer, 0, $length - $length % 4), true);
        if ($chunk === false) {
            throw new StreamException('Invalid base64 near offset ' . $this->offset);
        }

        $this->offset += $length - $length % 4;
        $this->buffer = \substr($this->buffer, $length - $length % 4);

        $this->destination->write($chunk);
    }

    public function end(string $finalData = ""): void
    {
        $this->offset += \strlen($this->buffer);

        $chunk = \base64_decode($this->buffer . $finalData, true);
        if ($chunk === false) {
            throw new StreamException('Invalid base64 near offset ' . $this->offset);
        }

        $this->buffer = '';

        $this->destination->end($chunk);
    }
}
