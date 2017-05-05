<?php

namespace Amp\ByteStream;

use Amp\Promise;
use function Amp\call;

class GzipInputStream implements InputStream {
    const STATE_FAILED = -1;
    const STATE_NORMAL = 0;
    const STATE_ENDING = 1;
    const STATE_ENDED = 2;

    private $source;
    private $resource;
    private $state = 0;

    public function __construct(InputStream $source) {
        $this->source = $source;
        $this->resource = \inflate_init(\ZLIB_ENCODING_GZIP);

        if ($this->resource === false) {
            throw new StreamException("Failed initializing deflate context");
        }
    }

    public function read(): Promise {
        return call(function () {
            if ($this->state === self::STATE_ENDED) {
                throw new ClosedException("The stream has already been closed");
            }

            if ($this->state === self::STATE_ENDING) {
                $this->state = self::STATE_ENDED;
                return null;
            }

            if ($this->state === self::STATE_FAILED) {
                throw new StreamException("The stream has previously failed");
            }

            $data = yield $this->source->read();

            if ($data === null) {
                $decompressed = \inflate_add($this->resource, "", \ZLIB_FINISH);

                $this->state = self::STATE_ENDING;

                if ($decompressed === false) {
                    $this->state = self::STATE_FAILED;
                    throw new StreamException("Failed adding data to deflate context");
                }

                return $decompressed;
            }

            $decompressed = \inflate_add($this->resource, $data, \ZLIB_SYNC_FLUSH);

            if ($decompressed === false) {
                $this->state = self::STATE_FAILED;
                throw new StreamException("Failed adding data to deflate context");
            }

            return $decompressed;
        });
    }

    public function close() {
        $this->state = self::STATE_ENDED;
        $this->source->close();
    }
}