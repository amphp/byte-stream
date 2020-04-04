<?php

/** @noinspection PhpComposerExtensionStubsInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\StreamException;
use Amp\Iterator;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\parseLineDelimitedJson;

class ParseLineDelimitedJsonTest extends AsyncTestCase
{
    public function test()
    {
        $result = yield Iterator\toArray(parseLineDelimitedJson(new InMemoryStream(\implode("\n", [
            \json_encode(['foo' => "\nbar\r\n"]),
            \json_encode(['foo' => []]),
        ]))));

        self::assertEquals([
            (object) ['foo' => "\nbar\r\n"],
            (object) ['foo' => []],
        ], $result);
    }

    public function testInvalidJson()
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to parse JSON');

        yield Iterator\toArray(parseLineDelimitedJson(new InMemoryStream('{')));
    }
}
