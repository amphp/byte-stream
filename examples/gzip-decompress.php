<?php

use Amp\ByteStream\ZlibInputStream;
use Amp\Loop;
use function Amp\ByteStream\getStdin;
use function Amp\ByteStream\getStdout;

require __DIR__."/../vendor/autoload.php";

Loop::run(function () {
    $stdin = getStdin();
    $stdout = getStdout();

    $gzin = new ZlibInputStream($stdin, ZLIB_ENCODING_GZIP);

    while (($chunk = yield $gzin->read()) !== null) {
        yield $stdout->write($chunk);
    }
});
