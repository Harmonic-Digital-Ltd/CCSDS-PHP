<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds\Oem;

/**
 * @api
 *
 * @implements \IteratorAggregate<int, StateVector>
 */
final readonly class OemSegment implements \IteratorAggregate
{
    /** @param iterable<int, StateVector> $stateVectors */
    public function __construct(
        public Metadata $metadata,
        /** @var iterable<int, StateVector> */
        public iterable $stateVectors,
    ) {}

    /** @return \Traversable<int, StateVector> */
    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->stateVectors;
    }
}
