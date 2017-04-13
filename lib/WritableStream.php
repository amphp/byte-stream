<?php

namespace Amp\ByteStream;

use Amp\Promise;

interface WritableStream {
    /**
     * @param string $data
     *
     * @return \Amp\Promise Succeeds once the data has been successfully written to the stream.
     */
    public function write(string $data): Promise;
    
    /**
     * @param string $data
     *
     * @return \Amp\Promise Succeeds once the data has been successfully written to the stream and the stream ended.
     */
    public function end(string $data = null): Promise;
}
