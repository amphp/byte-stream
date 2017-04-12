<?php

namespace Amp\ByteStream;

use Amp\Promise;

interface WritableStream {
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
}
