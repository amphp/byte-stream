<?php

namespace Amp\Stream;

use Interop\Async\Awaitable;

interface Stream {
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
     * @return \Interop\Async\Awaitable<string> Resolves with bytes read from the stream.
     */
    public function read(int $bytes = null, string $delimiter = null): Awaitable;
    
    /**
     * @param string $data
     *
     * @return \Interop\Async\Awaitable<int>
     */
    public function write(string $data): Awaitable;
    
    /**
     * @param string $data
     *
     * @return \Interop\Async\Awaitable<int>
     */
    public function end(string $data = ''): Awaitable;
    
    /**
     * Closes the stream and fails any pending reads or writes.
     */
    public function close();
}
