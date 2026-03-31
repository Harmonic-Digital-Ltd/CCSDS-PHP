<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds;

use HarmonicDigital\Ccsds\Exception\ParseException;
use HarmonicDigital\Ccsds\Oem\OemFile;
use HarmonicDigital\Ccsds\Parser\OemParser;
use HarmonicDigital\Ccsds\Parser\OemParserInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemReader;

/** @api */
final readonly class CcsdsClient implements CcsdsClientInterface
{
    public function __construct(
        private FilesystemReader $fs,
        private OemParserInterface $oemParser = new OemParser(),
    ) {}

    /**
     * @param string $location the path/filename of the OEM file to parse
     * @throws FilesystemException
     * @throws ParseException
     */
    #[\Override]
    public function parseOemFile(string $location): OemFile
    {
        return $this->oemParser->parseFromStream($this->fs->readStream($location));
    }
}
