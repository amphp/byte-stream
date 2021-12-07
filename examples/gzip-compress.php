<?php

use Amp\ByteStream\Compression\CompressingWritableStream;
use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;

require __DIR__ . "/../vendor/autoload.php";

$stdin = new ReadableResourceStream(STDIN);
$stdout = new WritableResourceStream(STDOUT);

$gzout = new CompressingWritableStream($stdout, ZLIB_ENCODING_GZIP);

while (($chunk = $stdin->read()) !== null) {
    $gzout->write($chunk);
}

$gzout->end();
