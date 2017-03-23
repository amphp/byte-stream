<?php

namespace Amp\ByteStream;

use Amp\Promise;

interface WritableStream {
    /**
     * Determines if the stream is writable.
     *
     * @return bool
     */
    public function isWritable(): bool;

    /**
     * @param string $data
     *
     * @return \Amp\Promise<int>
     */
    public function write(string $data): Promise;
    
    /**
     * @param string $data
     *
     * @return \Amp\Promise<int>
     */
    public function end(string $data = ''): Promise;
    
    /**
     * Closes the stream and fails any pending reads or writes.
     */
    public function close();
}
