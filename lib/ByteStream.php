<?php

namespace Amp\ByteStream;

use Amp\Promise;

interface ByteStream {
    /**
     * Determines if the stream is readable.
     *
     * @return bool
     */
    public function isReadable(): bool;
    
    /**
     * Determines if the stream is writable.
     *
     * @return bool
     */
    public function isWritable(): bool;
    
    /**
     * @param int|null $bytes
     * @param string|null $delimiter
     *
     * @return \Amp\Promise<string> Resolves with bytes read from the stream.
     */
    public function read(int $bytes = null, string $delimiter = null): Promise;
    
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
