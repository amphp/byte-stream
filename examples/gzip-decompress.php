<?php

use Amp\ByteStream\Compression\DecompressingReadableStream;
use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;

require __DIR__ . "/../vendor/autoload.php";

$stdin = new ReadableResourceStream(STDIN);
$stdout = new WritableResourceStream(STDOUT);

$gzin = new DecompressingReadableStream($stdin, ZLIB_ENCODING_GZIP);

while (($chunk = $gzin->read()) !== null) {
    $stdout->write($chunk);
}

$stdout->end();
