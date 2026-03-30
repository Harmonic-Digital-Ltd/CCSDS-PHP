<?php

declare(strict_types=1);

namespace Harmonicdigital\Ccsds\Oem;

/** @api */
final readonly class Header
{
    public function __construct(
        public string $version,
        public \DateTimeImmutable $creationDate,
        public string $originator,
    ) {}
}
