<?php

namespace Amp\ByteStream;

final class LineReader
{
    private string $delimiter;

    private bool $lineMode;

    private string $buffer = "";

    private InputStream $source;

    public function __construct(InputStream $inputStream, string $delimiter = null)
    {
        $this->source = $inputStream;
        $this->delimiter = $delimiter ?? "\n";
        $this->lineMode = $delimiter === null;
    }

    public function readLine(): ?string
    {
        if (\str_contains($this->buffer, $this->delimiter)) {
            [$line, $this->buffer] = \explode($this->delimiter, $this->buffer, 2);
            return $this->lineMode ? \rtrim($line, "\r") : $line;
        }

        while (null !== $chunk = $this->source->read()) {
            $this->buffer .= $chunk;

            if (\str_contains($this->buffer, $this->delimiter)) {
                [$line, $this->buffer] = \explode($this->delimiter, $this->buffer, 2);
                return $this->lineMode ? \rtrim($line, "\r") : $line;
            }
        }

        if ($this->buffer === "") {
            return null;
        }

        $line = $this->buffer;
        $this->buffer = "";
        return $this->lineMode ? \rtrim($line, "\r") : $line;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    /**
     * @return void
     */
    public function clearBuffer(): void
    {
        $this->buffer = "";
    }
}
