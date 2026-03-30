<?php

declare(strict_types=1);

namespace Harmonicdigital\Ccsds;

use Harmonicdigital\Ccsds\Oem\OemFile;
use Harmonicdigital\Ccsds\Parser\OemParser;
use League\Flysystem\FilesystemReader;

/** @api */
final readonly class Client
{
    public function __construct(
        private FilesystemReader $fs,
        private OemParser $oemParser = new OemParser(),
    ) {}

    public function parseOemFile(string $location): OemFile
    {
        return $this->oemParser->parseFromStream($this->fs->readStream($location));
    }
}
