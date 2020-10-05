<?php

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\ZlibOutputStream;

require __DIR__ . "/../vendor/autoload.php";

$stdin = new ResourceInputStream(STDIN);
$stdout = new ResourceOutputStream(STDOUT);

$gzout = new ZlibOutputStream($stdout, ZLIB_ENCODING_GZIP);

while (($chunk = $stdin->read()) !== null) {
    $gzout->write($chunk);
}

$gzout->end();
