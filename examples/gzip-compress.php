<?php

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\ZlibOutputStream;
use Concurrent\Task;

require __DIR__ . "/../vendor/autoload.php";

Task::await(Task::async(function () {
    $stdin = new ResourceInputStream(STDIN);
    $stdout = new ResourceOutputStream(STDOUT);

    $gzOut = new ZlibOutputStream($stdout, ZLIB_ENCODING_GZIP);

    while (($chunk = $stdin->read()) !== null) {
        $gzOut->write($chunk);
    }
}));
