<?php

namespace Amp\ByteStream;

use Amp\Promise;
use function Amp\call;

class BufferedInputStream implements InputStream {
    private $source;
    private $chunkSize;
    private $readOperation;

    public function __construct(InputStream $source, int $chunkSize = 8192) {
        $this->source = $source;
        $this->chunkSize = $chunkSize;
    }

    /**
     * Reads data from the stream.
     *
     * @return Promise Resolves with a string when new data is available or `null` if the stream has closed.
     *
     * @throws PendingReadException Thrown if another read operation is still pending.
     */
    public function read(): Promise {
        if ($this->readOperation !== null) {
            throw new PendingReadException;
        }

        $this->readOperation = call(function () {
            $buffer = "";

            while (($chunk = yield $this->source->read()) !== null) {
                $buffer .= "";

                if (isset($buffer[$this->chunkSize - 1])) {
                    return $buffer;
                }
            }

            return $buffer;
        });

        return $this->readOperation;
    }

    /**
     * Closes the stream forcefully. Multiple `close()` calls are ignored.
     *
     * Note: If a class implements `InputStream` and `OutputStream`, `close()` will close both streams at once. If you
     * want to allow half-closed duplex streams, you must use different objects for input and output.
     *
     * @return void
     */
    public function close() {
        $this->source->close();
    }
}