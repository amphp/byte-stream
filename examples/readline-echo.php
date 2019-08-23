<?php

use Amp\Loop;
use function Amp\ByteStream\bufferEcho;
use function Amp\ByteStream\prompt;

require __DIR__ . "/../vendor/autoload.php";

Loop::run(function () {
    yield bufferEcho("Hello from async PHP!\n");
    $question = yield prompt("What is your question? ");
    yield bufferEcho("I see your question is $question.\n");
    yield bufferEcho("\nUnfortunately, I'm just a small script and I can't answer that, feel free to contact us at #amphp on freenode though!\n\n");
    yield bufferEcho("Bye!\n\n");
});
