<?php

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\ByteStream\ZlibWritableStream;

require __DIR__ . "/../vendor/autoload.php";

$stdin = new ReadableResourceStream(STDIN);
$stdout = new WritableResourceStream(STDOUT);

$gzout = new ZlibWritableStream($stdout, ZLIB_ENCODING_GZIP);

while (($chunk = $stdin->read()) !== null) {
    $gzout->write($chunk);
}

$gzout->end();
