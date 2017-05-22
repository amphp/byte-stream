# IteratorStream

`IteratorStream` allows converting an `Amp\Iterator` that yields strings into an `InputStream`.

```php
$emitter = new Emitter;

asyncCoroutine(function () use ($emitter) {
    for ($i = 0; $i < 10; $i++) {
        yield new Delayed(1000);
        yield $emitter->emit(".");
    }

    $emitter->complete();
});

$inputStream = new IteratorStream($emitter->iterate());
```
