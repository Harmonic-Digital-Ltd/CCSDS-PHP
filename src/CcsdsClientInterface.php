<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds;

use HarmonicDigital\Ccsds\Oem\OemFile;

/** @api */
interface CcsdsClientInterface
{
    public function parseOemFile(string $location): OemFile;
}
