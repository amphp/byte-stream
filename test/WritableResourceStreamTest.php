<?php declare(strict_types=1);

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use function Amp\delay;

final class WritableResourceStreamTest extends AsyncTestCase
{
    public function testGetResource(): void
    {
        $stream = new WritableResourceStream(\STDOUT);

        self::assertSame(\STDOUT, $stream->getResource());
    }

    public function testNonStream(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a valid stream");

        /** @noinspection PhpParamsInspection */
        new WritableResourceStream(42);
    }

    public function testNotWritable(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a writable stream");

        new WritableResourceStream(\STDIN);
    }

    public function testBrokenPipe(): void
    {
        if (($sockets = @\stream_socket_pair(
            \PHP_OS_FAMILY === 'Windows' ? STREAM_PF_INET : STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        )) === false) {
            self::fail("Failed to create socket pair.");
        }

        [$a, $b] = $sockets;

        $stream = new WritableResourceStream($a);
        \fclose($b);

        $this->expectException(StreamException::class);

        if (\PHP_OS_FAMILY === 'Windows') {
            $this->expectExceptionMessage(/* S|s */ 'end of 6 bytes failed with errno=10053 An established connection was aborted by the software in your host machine');
        } else {
            $this->expectExceptionMessage(/* S|s */ "end of 6 bytes failed with errno=32 Broken pipe");
        }

        $stream->write("foobar");
        $stream->write("foobar");
    }

    public function testClosedRemoteSocket(): void
    {
        $server = \stream_socket_server("tcp://127.0.0.1:0");
        $address = \stream_socket_get_name($server, false);

        $a = \stream_socket_client("tcp://" . $address);
        $b = \stream_socket_accept($server);

        $stream = new WritableResourceStream($a);
        \fclose($b);

        $this->expectException(StreamException::class);

        if (\PHP_OS_FAMILY === 'Windows') {
            $this->expectExceptionMessage(/* S|s */ 'end of 6 bytes failed with errno=10053 An established connection was aborted by the software in your host machine');
        } else {
            $this->expectExceptionMessage(/* S|s */ "end of 6 bytes failed with errno=32 Broken pipe");
        }

        // The first write still succeeds somehow...
        $stream->write("foobar");
        delay(0.1); // Provide some time for the OS to mark the socket is closed.
        $stream->write("foobar");
    }

    /**
     * @see https://github.com/reactphp/stream/pull/150
     */
    public function testUploadBiggerBlockSecure(): void
    {
        $size = 2 ** 18; // 256kb

        $resource = \stream_socket_client('tls://httpbin.org:443');

        $output = new WritableResourceStream($resource);
        $input = new ReadableResourceStream($resource);

        $body = \str_repeat('.', $size);
        $output->write("POST /post HTTP/1.0\r\nHost: httpbin.org\r\nContent-Length: $size\r\n\r\n" . $body);

        $this->assertStringContainsString($body, buffer($input));
    }

    /**
     * A peer that is gone leaves the descriptor permanently writable as far as the event
     * loop is concerned, so a failed write must be recognised as a failure rather than as
     * a partial write. On a TLS stream that cannot be done from $errorCode alone: fwrite()
     * returns int(0) and PHP stops raising a warning once the stream's EOF flag is set,
     * which is exactly what reading to EOF does. Treating it as a partial write re-queues
     * the chunk and returns with the watcher still enabled, spinning the loop forever.
     */
    public function testWriteFailsWhenTlsPeerIsGone(): void
    {
        if (!\extension_loaded('openssl')) {
            self::markTestSkipped('ext-openssl is required to establish a TLS connection');
        }

        $this->setTimeout(5);

        [$connection, $client] = $this->createTlsPair();

        \fclose($client);

        // Reading to EOF sets the stream's EOF flag; from that point fwrite() on the same
        // resource returns 0 without reporting anything on affected OpenSSL builds.
        $input = new ReadableResourceStream($connection);
        self::assertNull($input->read());

        // Not every build goes quiet. Where fwrite() still reports the failure the existing
        // $errorCode check already catches it and there is nothing here to exercise.
        $reported = false;
        \set_error_handler(static function () use (&$reported): bool {
            $reported = true;

            return true;
        });

        try {
            \fwrite($connection, 'probe');
        } finally {
            \restore_error_handler();
        }

        if ($reported) {
            self::markTestSkipped('This build reports failed writes to a closed TLS peer, so $errorCode suffices');
        }

        $output = new WritableResourceStream($connection);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to write to stream');

        $output->write('foobar');
    }

    /**
     * @return array{resource, resource} Server side and client side of an established TLS
     *     connection over loopback.
     */
    private function createTlsPair(): array
    {
        $certificate = $this->createCertificate();

        $server = \stream_socket_server(
            'tcp://127.0.0.1:0',
            $errno,
            $errstr,
            \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN,
            \stream_context_create(['ssl' => [
                'local_cert' => $certificate,
                'allow_self_signed' => true,
                'verify_peer' => false,
            ]]),
        );

        if ($server === false) {
            self::fail("Failed to create TLS server: $errstr");
        }

        $address = \stream_socket_get_name($server, false);

        $client = \stream_socket_client(
            "tcp://$address",
            $errno,
            $errstr,
            5,
            \STREAM_CLIENT_CONNECT,
            \stream_context_create(['ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]]),
        );

        if ($client === false) {
            self::fail("Failed to connect to TLS server: $errstr");
        }

        $connection = \stream_socket_accept($server, 5);
        if ($connection === false) {
            self::fail('Failed to accept TLS connection');
        }

        \fclose($server);

        // Both handshakes have to progress in this single process, so drive them
        // alternately on non-blocking sockets until both report completion.
        \stream_set_blocking($connection, false);
        \stream_set_blocking($client, false);

        $serverDone = false;
        $clientDone = false;
        $deadline = \microtime(true) + 5;

        while (!$serverDone || !$clientDone) {
            if (!$serverDone) {
                $serverDone = @\stream_socket_enable_crypto($connection, true, \STREAM_CRYPTO_METHOD_TLS_SERVER) === true;
            }

            if (!$clientDone) {
                $clientDone = @\stream_socket_enable_crypto($client, true, \STREAM_CRYPTO_METHOD_TLS_CLIENT) === true;
            }

            if (\microtime(true) > $deadline) {
                \unlink($certificate);
                self::fail('TLS handshake did not complete');
            }

            \usleep(1000);
        }

        \unlink($certificate);

        \stream_set_blocking($connection, true);
        \stream_set_blocking($client, true);

        return [$connection, $client];
    }

    private function createCertificate(): string
    {
        $key = \openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        $csr = \openssl_csr_new(['commonName' => 'localhost'], $key, ['digest_alg' => 'sha256']);
        $certificate = \openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);

        if ($key === false || $csr === false || $certificate === false) {
            self::markTestSkipped('Failed to generate a self-signed certificate');
        }

        \openssl_x509_export($certificate, $certificateOut);
        \openssl_pkey_export($key, $keyOut);

        $path = \tempnam(\sys_get_temp_dir(), 'amp-byte-stream-tls-');
        if ($path === false) {
            self::fail('Failed to create a temporary file for the certificate');
        }

        \file_put_contents($path, $certificateOut . $keyOut);

        return $path;
    }
}
