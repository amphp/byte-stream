<?php declare(strict_types=1);

namespace Amp\ByteStream;

use Amp\Future;
use Amp\PHPUnit\AsyncTestCase;

final class AsyncWriterTest extends AsyncTestCase
{
    public function testWriting(): void
    {
        $chunks = ['Hello, world!', '0', 'Lorem Ipsum dolor sit amet'];

        $pipe = new Pipe(0);
        $source = $pipe->getSource();
        $asyncWriter = new AsyncWriter($pipe->getSink());

        $futures = \array_map(fn (string $bytes) => $asyncWriter->write($bytes), $chunks);

        foreach ($chunks as $chunk) {
            self::assertSame($chunk, $source->read());
        }

        Future\await($futures);
    }

    public function testEnd(): void
    {
        $chunk = 'Hello, world!';

        $pipe = new Pipe(0);
        $source = $pipe->getSource();
        $sink = $pipe->getSink();
        $asyncWriter = new AsyncWriter($pipe->getSink());

        $future1 = $asyncWriter->write($chunk);
        $future2 = $asyncWriter->end();

        self::assertTrue($sink->isWritable());
        self::assertFalse($asyncWriter->isWritable());

        self::assertSame($chunk, $source->read());

        Future\await([$future1, $future2]);

        self::assertFalse($sink->isWritable());
        self::assertNull($source->read());
    }
}
