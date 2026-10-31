<?php declare(strict_types=1);

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use function Amp\delay;

/**
 * Base test case with TLS stream helper methods for testing.
 */
abstract class TlsStreamTestCase extends AsyncTestCase
{
    /**
     * Creates a pair of connected TLS streams.
     *
     * @return array{resource, resource} Server side and client TLS pair.
     */
    protected function createTlsPair(): array
    {
        if (!\extension_loaded('openssl')) {
            self::markTestSkipped('ext-openssl is required to establish a TLS connection');
        }

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

            delay(0.01);
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
