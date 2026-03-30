<?php

declare(strict_types=1);

namespace Harmonicdigital\Ccsds\Oem;

/**
 * @api
 * @implements \IteratorAggregate<int, OemSegment>
 */
final readonly class OemFile implements \IteratorAggregate
{
    /**
     * @param string[]                  $comments File-level comments from the header section.
     * @param iterable<int, OemSegment> $segments Segments of the file, in order.
     */
    public function __construct(
        public Header $header,
        public array $comments,
        public iterable $segments,
    ) {}

    /**
     * Returns this file as an iterable over its segments.
     * Segments are cached after the first pass; subsequent iterations replay from cache.
     *
     * @return static
     */
    public function segments(): static
    {
        return $this;
    }

    /**
     * Each call returns a fresh generator that replays the cache then streams the rest.
     * This means the file can be iterated multiple times without re-reading the source.
     *
     * @return \Traversable<int, OemSegment>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->segments;
    }
}
