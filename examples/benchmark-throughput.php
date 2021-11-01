<?php

// Adopted from ReactPHP's stream package
// https://github.com/reactphp/stream/blob/b996af99fd1169ff74e93ef69c1513b7d0db19d0/examples/benchmark-throughput.php

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;

require __DIR__ . '/../vendor/autoload.php';

EventLoop::setDriver(new StreamSelectDriver);

$args = \getopt('i:o:t:');
$if = $args['i'] ?? '/dev/zero';
$of = $args['o'] ?? '/dev/null';
$t = (int) ($args['t'] ?? 30);

\assert(\is_string($if) && \is_string($of));

// passing file descriptors requires mapping paths (https://bugs.php.net/bug.php?id=53465)
$if = \preg_replace('(^/dev/fd/)', 'php://fd/', $if);
$of = \preg_replace('(^/dev/fd/)', 'php://fd/', $of);

$stderr = new ResourceOutputStream(STDERR);
$in = new ResourceInputStream(\fopen($if, 'rb'), 65536 /* Default size used by React to allow comparisons */);
$out = new ResourceOutputStream(\fopen($of, 'wb'));

if (\extension_loaded('xdebug')) {
    $stderr->write('NOTICE: The "xdebug" extension is loaded, this has a major impact on performance.' . PHP_EOL);
}

try {
    if (!@\assert(false)) {
        $stderr->write("NOTICE: Assertions are enabled, this has a major impact on performance." . PHP_EOL);
    }
} catch (AssertionError $exception) {
    $stderr->write("NOTICE: Assertions are enabled, this has a major impact on performance." . PHP_EOL);
}

$stderr->write('piping from ' . $if . ' to ' . $of . ' (for max ' . $t . ' second(s)) ...' . PHP_EOL);

EventLoop::delay($t, [$in, "close"]);

$start = \microtime(true);
$bytes = 0;

while (($chunk = $in->read()) !== null) {
    $out->write($chunk);
    $bytes += \strlen($chunk);
}

$t = \microtime(true) - $start;

$resource = $out->getResource();
\assert($resource !== null);

$bytesFormatted = \round($bytes / 1024 / 1024 / $t, 1);

$stderr->write('read ' . $bytes . ' byte(s) in ' . \round($t, 3) . ' second(s) => ' . $bytesFormatted . ' MiB/s' . PHP_EOL);
$stderr->write('peak memory usage of ' . \round(\memory_get_peak_usage(true) / 1024 / 1024, 1) . ' MiB' . PHP_EOL);
