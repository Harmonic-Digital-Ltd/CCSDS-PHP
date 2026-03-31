<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds\Oem;

/**
 * @api
 *
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
     * @return \Traversable<int, OemSegment>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->segments;
    }
}
