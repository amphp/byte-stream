<?php

namespace Amp\Stream;

interface Stream {
    /**
     * Determines if the stream is readable.
     *
     * @return bool
     */
    public function isReadable();
    
    /**
     * Determines if the stream is writable.
     *
     * @return bool
     */
    public function isWritable();
    
    /**
     * @param int|null $bytes
     * @param string|null $delimiter
     *
     * @return \Interop\Async\Awaitable<string> Resolves with bytes read from the stream.
     */
    public function read($bytes = null, $delimiter = null);
    
    /**
     * @param string $data
     *
     * @return \Interop\Async\Awaitable<int>
     */
    public function write($data);
    
    /**
     * @param string $data
     *
     * @return \Interop\Async\Awaitable<int>
     */
    public function end($data = '');
    
    /**
     * Closes the stream and fails any pending reads or writes.
     */
    public function close();
}
