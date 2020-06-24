<?php

namespace Amp\ByteStream;

use Amp\Promise;
use function Amp\call;

final class LineReader
{
    const DEFAULT_ENDING = "\n";

    /** @var string */
    private $ending;

    /** @var string */
    private $buffer = "";

    /** @var InputStream */
    private $source;

    public function __construct(InputStream $inputStream, string $ending = self::DEFAULT_ENDING)
    {
        $this->source = $inputStream;
        $this->ending = $ending;
    }

    /**
     * @return Promise<string|null>
     */
    public function readLine(): Promise
    {
        return call(function () {
            if (false !== strpos($this->buffer, $this->ending)) {
                [$line, $this->buffer] = explode($this->ending, $this->buffer, 2);
                return $this->ending === self::DEFAULT_ENDING ? \rtrim($line, "\r") : $line;
            }

            while (null !== $chunk = yield $this->source->read()) {
                $this->buffer .= $chunk;

                if (false !== \strpos($this->buffer, $this->ending)) {
                    [$line, $this->buffer] = explode($this->ending, $this->buffer, 2);
                    return $this->ending === self::DEFAULT_ENDING ? \rtrim($line, "\r") : $line;
                }
            }

            if ($this->buffer === "") {
                return null;
            }

            $line = $this->buffer;
            $this->buffer = "";
            return $this->ending === self::DEFAULT_ENDING ? \rtrim($line, "\r") : $line;
        });
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    /**
     * @return void
     */
    public function clearBuffer()
    {
        $this->buffer = "";
    }
}
