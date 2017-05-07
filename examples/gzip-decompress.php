<?php

use Amp\ByteStream\GzipInputStream;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Loop;

require __DIR__ . "/../vendor/autoload.php";

Loop::run(function () {
    $stdin = new ResourceInputStream(STDIN);
    $stdout = new ResourceOutputStream(STDOUT);

    $gzin = new GzipInputStream($stdin);

    while (($chunk = yield $gzin->read()) !== null) {
        yield $stdout->write($chunk);
    }
});