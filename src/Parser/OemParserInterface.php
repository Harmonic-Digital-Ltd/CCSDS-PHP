<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds\Parser;

use HarmonicDigital\Ccsds\Exception\ParseException;
use HarmonicDigital\Ccsds\Oem\OemFile;

/** @api */
interface OemParserInterface
{
    /**
     * @param resource $stream
     *
     * @throws ParseException
     */
    public function parseFromStream($stream): OemFile;
}
