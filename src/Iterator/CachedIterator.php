<?php

declare(strict_types=1);

namespace Harmonicdigital\Ccsds\Iterator;

use Generator;

/**
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
final class CachedIterator implements \IteratorAggregate
{
    /** @var list<T> */
    private array $cache = [];

    private bool $exhausted = false;

    /** @var \Generator<int, T> */
    private \Generator $generator;

    /**
     * @param iterable<int, T> $iterable
     */
    public function __construct(iterable $iterable)
    {

        $this->generator = (static function () use ($iterable): \Generator {
            yield from $iterable;
        })();
    }

    /**
     * Each call returns a fresh generator that replays the cache then streams the rest.
     * This means the file can be iterated multiple times without re-reading the source.
     *
     * @return \Generator<int<0, max>, T>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        foreach ($this->cache as $i => $segment) {
            yield $i => $segment;
        }

        $index = (array_key_last($this->cache) ?? -1) + 1;

        while (!$this->exhausted && $this->generator->valid()) {
            /** @var T $segment */
            $segment = $this->generator->current();
            $this->cache[] = $segment;
            $this->generator->next();
            yield $index++ => $segment;
        }

        $this->exhausted = true;
    }
}
