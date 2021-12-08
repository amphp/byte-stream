<?php

/** @noinspection PhpComposerExtensionStubsInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;

final class ParseLineDelimitedJsonTest extends AsyncTestCase
{
    public function test(): void
    {
        $result = \iterator_to_array(parseLineDelimitedJson(new ReadableBuffer(\implode("\n", [
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

        \iterator_to_array(parseLineDelimitedJson(new ReadableBuffer('{')));
    }
}
