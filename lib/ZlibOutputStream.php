<?php

namespace Amp\ByteStream;

use Amp\Promise;

class ZlibOutputStream implements OutputStream {
    private $destination;
    private $encoding;
    private $resource;

    public function __construct(OutputStream $destination, int $encoding) {
        $validEncodings = [\ZLIB_ENCODING_GZIP, \ZLIB_ENCODING_DEFLATE, \ZLIB_ENCODING_RAW];

        if (!in_array($encoding, $validEncodings, true)) {
            throw new \Error("Invalid encoding: " . $encoding);
        }

        $this->destination = $destination;
        $this->encoding = $encoding;
        $this->resource = \deflate_init($encoding);

        if ($this->resource === false) {
            throw new StreamException("Failed initializing deflate context");
        }
    }

    public function write(string $data): Promise {
        if ($this->resource === null) {
            throw new ClosedException("The stream has already been closed");
        }

        $compressed = \deflate_add($this->resource, $data, \ZLIB_SYNC_FLUSH);

        if ($compressed === false) {
            throw new StreamException("Failed adding data to deflate context");
        }

        $promise = $this->destination->write($compressed);
        $promise->onResolve(function ($error) {
            if ($error) {
                $this->close();
            }
        });

        return $promise;
    }

    public function end(string $finalData = ""): Promise {
        if ($this->resource === null) {
            throw new ClosedException("The stream has already been closed");
        }

        $compressed = \deflate_add($this->resource, $finalData, \ZLIB_FINISH);

        if ($compressed === false) {
            throw new StreamException("Failed adding data to deflate context");
        }

        $promise = $this->destination->write($compressed);
        $promise->onResolve(function ($error) {
            if ($error) {
                $this->close();
            }
        });

        return $promise;
    }

    protected function close() {
        $this->resource = null;
        $this->destination = null;
    }

    public function getEncoding(): int {
        return $this->encoding;
    }
}
