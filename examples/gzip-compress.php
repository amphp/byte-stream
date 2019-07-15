<?php

use Amp\ByteStream\ZlibOutputStream;
use Amp\Loop;
use function Amp\ByteStream\getStdin;
use function Amp\ByteStream\getStdout;

require __DIR__."/../vendor/autoload.php";

Loop::run(function () {
    $stdin = getStdin();
    $stdout = getStdout();

    $gzout = new ZlibOutputStream($stdout, ZLIB_ENCODING_GZIP);

    while (($chunk = yield $stdin->read()) !== null) {
        yield $gzout->write($chunk);
    }
});
