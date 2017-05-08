<?php

namespace Amp\ByteStream;

use Amp\Promise;
use function Amp\call;

class GzipInputStream implements InputStream {
    private $source;
    private $resource;

    public function __construct(InputStream $source) {
        $this->source = $source;
        $this->resource = \inflate_init(\ZLIB_ENCODING_GZIP);

        if ($this->resource === false) {
            throw new StreamException("Failed initializing deflate context");
        }
    }

    public function read(): Promise {
        return call(function () {
            if ($this->resource === null) {
                return null;
            }

            $data = yield $this->source->read();

            // Needs a double guard, as stream might have been closed while reading
            if ($this->resource === null) {
                return null;
            }

            if ($data === null) {
                $decompressed = \inflate_add($this->resource, "", \ZLIB_FINISH);

                if ($decompressed === false) {
                    throw new StreamException("Failed adding data to deflate context");
                }

                $this->close();

                return $decompressed;
            }

            $decompressed = \inflate_add($this->resource, $data, \ZLIB_SYNC_FLUSH);

            if ($decompressed === false) {
                throw new StreamException("Failed adding data to deflate context");
            }

            return $decompressed;
        });
    }

    public function close() {
        $this->resource = null;

        $this->source->close();
        $this->source = null;
    }
}
