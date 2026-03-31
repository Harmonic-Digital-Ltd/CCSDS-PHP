<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds\Oem;

/**
 * Position in km, velocity in km/s. Stored as numeric strings to preserve exact decimal precision.
 *
 * @api
 */
final readonly class StateVector
{
    /**
     * @param numeric-string $x
     * @param numeric-string $y
     * @param numeric-string $z
     * @param numeric-string $xDot
     * @param numeric-string $yDot
     * @param numeric-string $zDot
     */
    public function __construct(
        public \DateTimeImmutable $epoch,
        public string $x,
        public string $y,
        public string $z,
        public string $xDot,
        public string $yDot,
        public string $zDot,
    ) {}
}
