<?php

require __DIR__ . "/../vendor/autoload.php";

use Amp\ByteStream\Parser;
use Amp\Loop;

Loop::run(function () {
    // Defines a generator that yields integers (number of bytes to read), strings (delimiter to search for), or
    // null (read any amount of bytes).
    $generator = function (callable $printer): \Generator {
        while (true) {
            $buffer = yield "\n"; // Reads until a new-line character is found.
            $printer($buffer); // Use the received data.
        }
    };

    // The user of Parser is responsible for creating the Generator object, allowing anything to be passed into the
    // generator that may be required.
    $parser = new Parser($generator(function (string $parsedData) {
        static $i = 0;
        printf("[%d] %s\n", $i++, $parsedData);
    }));

    $parser->write("This\nis\n");

    Loop::delay(1000, function () use ($parser) {
        $parser->write("an\nexample\nof\n");
    });

    Loop::delay(2000, function () use ($parser) {
        $parser->write("a\nsimple\n");
    });

    Loop::delay(3000, function () use ($parser) {
        $parser->write("incremental\nstream\nparser\n");
    });

    Loop::delay(4000, function () use ($parser) {
        $parser->end(); // Marks the end of data.
    });
});
