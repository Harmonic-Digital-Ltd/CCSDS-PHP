<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds\Iterator;

/**
 * @template TKey of array-key
 * @template TValue of mixed
 *
 * @implements \IteratorAggregate<TKey, TValue>
 */
final class CachedIterator implements \IteratorAggregate
{
    /** @var array<TKey, TValue> */
    private array $cache = [];

    private bool $exhausted = false;

    /**
     * @param \Iterator<TKey, TValue> $iterable
     */
    public function __construct(private readonly \Iterator $iterable) {}

    /**
     * Each call returns a fresh generator that replays the cache then streams the rest.
     * This means the file can be iterated multiple times without re-reading the source.
     *
     * @return \Generator<TKey, TValue>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        foreach ($this->cache as $i => $segment) {
            yield $i => $segment;
        }

        while (!$this->exhausted && $this->iterable->valid()) {
            /** @var TValue $segment */
            $segment = $this->iterable->current();
            $key = $this->iterable->key();
            if (null === $key) {
                break;
            }
            $this->cache[$key] = $segment;
            $this->iterable->next();
            yield $key => $segment;
        }

        $this->exhausted = true;
    }
}
