<?php

namespace Amp\ByteStream;

use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Serialization\SerializationException;
use Amp\Sync\ChannelException;
use Amp\TimeoutCancellation;
use function Amp\async;

class StreamChannelTest extends AsyncTestCase
{
    public function testSendReceive(): void
    {
        $pipe = new Pipe(0);
        $channel = new StreamChannel($pipe->getSource(), $pipe->getSink());

        $message = 'hello';

        async(fn () => $channel->send($message));
        $data = $channel->receive();
        $this->assertSame($message, $data);
    }

    public function testSendReceiveBuffered(): void
    {
        $pipe = new Pipe(10000);
        $channel = new StreamChannel($pipe->getSource(), $pipe->getSink());

        $message = 'hello';
        $encoded = \serialize($message);
        $encoded = \pack('CL', 0, \strlen($encoded)) . $encoded;

        $pipe->getSink()->write($encoded . $encoded);
        $data = $channel->receive();
        $this->assertSame($message, $data);
    }

    public function testCancelReceive(): void
    {
        $pipe = new Pipe(0);
        $channel = new StreamChannel($pipe->getSource(), $pipe->getSink());

        $message = 'hello';
        $encoded = \serialize($message);
        $encoded = \pack('CL', 0, \strlen($encoded)) . $encoded;

        try {
            $deferredCancellation = new DeferredCancellation();
            $deferredCancellation->cancel();
            $channel->receive($deferredCancellation->getCancellation());

            self::fail('Expected CancelledException has not been thrown');
        } catch (CancelledException) {
            // ignore exception
        }

        async(fn () => $pipe->getSink()->write($encoded));
        $data = $channel->receive(new TimeoutCancellation(0));
        $this->assertSame($message, $data);
    }

    /**
     * @depends testSendReceive
     */
    public function testSendReceiveLongData(): void
    {
        $pipe = new Pipe(0);
        $channel = new StreamChannel($pipe->getSource(), $pipe->getSink());

        $length = 0xffff;
        $message = '';
        for ($i = 0; $i < $length; ++$i) {
            $message .= \chr(\mt_rand(0, 255));
        }

        async(fn () => $channel->send($message));
        $data = $channel->receive();
        $this->assertSame($message, $data);
    }

    /**
     * @depends testSendReceive
     */
    public function testInvalidDataReceived(): void
    {
        $this->expectException(ChannelException::class);

        $pipe = new Pipe(0);
        $sink = $pipe->getSink();
        $channel = new StreamChannel($pipe->getSource(), $sink);

        // Close $a. $b should close on next read...
        async(fn () => $sink->write(\pack('L', 10) . '1234567890'));
        $data = $channel->receive();
    }

    /**
     * @depends testSendReceive
     */
    public function testSendUnserializableData(): void
    {
        $this->expectException(SerializationException::class);

        $pipe = new Pipe(0);
        $sink = $pipe->getSink();
        $channel = new StreamChannel($pipe->getSource(), $sink);

        // Close $a. $b should close on next read...
        $channel->send(fn () => null);
        $data = $channel->receive();
    }

    /**
     * @depends testSendReceive
     */
    public function testSendAfterClose(): void
    {
        $this->expectException(ChannelException::class);

        $mock = $this->createMock(WritableStream::class);
        $mock->expects($this->once())
            ->method('write')
            ->will($this->throwException(new StreamException));

        $a = new StreamChannel($this->createMock(ReadableStream::class), $mock);
        $b = new StreamChannel(
            $this->createMock(ReadableStream::class),
            $this->createMock(WritableStream::class)
        );

        $a->send('hello');
    }

    /**
     * @depends testSendReceive
     */
    public function testReceiveAfterClose(): void
    {
        $mock = $this->createMock(ReadableStream::class);
        $mock->expects($this->once())
            ->method('read')
            ->willReturn(null);

        $a = new StreamChannel($mock, $this->createMock(WritableStream::class));

        $this->expectException(ChannelException::class);
        $this->expectExceptionMessage('channel closed');
        $a->receive();
    }
}
