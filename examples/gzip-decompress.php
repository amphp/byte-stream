<?php

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\ZlibInputStream;
use Amp\Loop;

require __DIR__ . "/../vendor/autoload.php";

$stdin = new ResourceInputStream(STDIN);
$stdout = new ResourceOutputStream(STDOUT);

$gzin = new ZlibInputStream($stdin, ZLIB_ENCODING_GZIP);

while (($chunk = $gzin->read()) !== null) {
    $stdout->write($chunk);
}

$stdout->end();
