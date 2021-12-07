<?php

/** @noinspection PhpComposerExtensionStubsInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

class ParseLineDelimitedJsonTest extends AsyncTestCase
{
    public function test(): void
    {
        $result = Pipeline\toArray(parseLineDelimitedJson(new ReadBuffer(\implode("\n", [
            \json_encode(['foo' => "\nbar\r\n"]),
            \json_encode(['foo' => []]),
        ]))));

        self::assertEquals([
            (object) ['foo' => "\nbar\r\n"],
            (object) ['foo' => []],
        ], $result);
    }

    public function testInvalidJson(): void
    {
        $this->expectException(\JsonException::class);
        $this->expectExceptionMessage('Syntax error');

        Pipeline\toArray(parseLineDelimitedJson(new ReadBuffer('{')));
    }
}
