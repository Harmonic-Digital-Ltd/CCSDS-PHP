<?php

declare(strict_types=1);

namespace Harmonicdigital\Ccsds\Oem;

/** @api */
final readonly class Metadata
{
    public function __construct(
        public string $objectName,
        public string $objectId,
        public string $centerName,
        public string $refFrame,
        public string $timeSystem,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $stopTime,
        public ?string $refFrameEpoch = null,
        public ?\DateTimeImmutable $useableStartTime = null,
        public ?\DateTimeImmutable $useableStopTime = null,
        public ?string $interpolation = null,
        public ?int $interpolationDegree = null,
    ) {}
}
