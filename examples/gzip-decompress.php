<?php

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\ZlibInputStream;
use Concurrent\Task;

require __DIR__ . "/../vendor/autoload.php";

Task::await(Task::async(function () {
    $stdin = new ResourceInputStream(STDIN);
    $stdout = new ResourceOutputStream(STDOUT);

    $gzIn = new ZlibInputStream($stdin, ZLIB_ENCODING_GZIP);

    while (($chunk = $gzIn->read()) !== null) {
        $stdout->write($chunk);
    }
}));
