<?php

declare(strict_types=1);

namespace Harmonicdigital\Ccsds\Oem;

/** @api */
final readonly class OemSegment
{
    /** @param iterable<int, StateVector> $stateVectors */
    public function __construct(
        public Metadata $metadata,
        public iterable $stateVectors,
    ) {}
}
