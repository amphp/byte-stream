<?php

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\ByteStream\ZlibReadableStream;

require __DIR__ . "/../vendor/autoload.php";

$stdin = new ReadableResourceStream(STDIN);
$stdout = new WritableResourceStream(STDOUT);

$gzin = new ZlibReadableStream($stdin, ZLIB_ENCODING_GZIP);

while (($chunk = $gzin->read()) !== null) {
    $stdout->write($chunk);
}

$stdout->end();
