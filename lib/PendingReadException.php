<?php

namespace Amp\ByteStream;

use Throwable;

/**
 * Thrown in case a second read operation is attempted while another read operation is still pending.
 */
class PendingReadException extends StreamException {
    public function __construct(
        $message = "The previous read operation must complete before read can be called again",
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
