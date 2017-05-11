<?php

namespace Amp\ByteStream;

use Amp\Promise;

class GzipOutputStream implements OutputStream {
    private $destination;
    private $resource;

    public function __construct(OutputStream $destination) {
        $this->destination = $destination;
        $this->resource = \deflate_init(\ZLIB_ENCODING_GZIP);

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
}
