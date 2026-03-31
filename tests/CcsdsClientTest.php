<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds\Tests;

use HarmonicDigital\Ccsds\CcsdsClient;
use HarmonicDigital\Ccsds\Exception\ParseException;
use HarmonicDigital\Ccsds\Oem\Header;
use HarmonicDigital\Ccsds\Oem\OemFile;
use HarmonicDigital\Ccsds\Parser\OemParserInterface;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CcsdsClient::class)]
final class CcsdsClientTest extends TestCase
{
    public function testParseOemFileDelegatesToFsAndParser(): void
    {
        $stream = fopen('php://memory', 'rb');
        $this->assertIsResource($stream);

        $oemFile = $this->makeOemFile();

        $fs = $this->createMock(FilesystemReader::class);
        $fs->expects($this->once())
            ->method('readStream')
            ->with('path/to/file.oem')
            ->willReturn($stream);

        $parser = $this->createMock(OemParserInterface::class);
        $parser->expects($this->once())
            ->method('parseFromStream')
            ->with($stream)
            ->willReturn($oemFile);

        $result = new CcsdsClient($fs, $parser)->parseOemFile('path/to/file.oem');

        $this->assertSame($oemFile, $result);
    }

    public function testParseOemFilePropagatesUnableToReadFile(): void
    {
        $fs = $this->createStub(FilesystemReader::class);
        $fs->method('readStream')->willThrowException(UnableToReadFile::fromLocation('missing.oem'));

        $parser = $this->createStub(OemParserInterface::class);

        $this->expectException(UnableToReadFile::class);

        new CcsdsClient($fs, $parser)->parseOemFile('missing.oem');
    }

    public function testParseOemFilePropagatesParseException(): void
    {
        $stream = fopen('php://memory', 'rb');
        $this->assertIsResource($stream);

        $fs = $this->createStub(FilesystemReader::class);
        $fs->method('readStream')->willReturn($stream);

        $parser = $this->createStub(OemParserInterface::class);
        $parser->method('parseFromStream')->willThrowException(new ParseException('Bad OEM data'));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Bad OEM data');

        new CcsdsClient($fs, $parser)->parseOemFile('corrupt.oem');
    }

    public function testStreamReturnedByFsIsPassedDirectlyToParser(): void
    {
        $stream = fopen('php://memory', 'rb');
        $this->assertIsResource($stream);

        $fs = $this->createStub(FilesystemReader::class);
        $fs->method('readStream')->willReturn($stream);

        $capturedStream = null;
        $parser = $this->createStub(OemParserInterface::class);
        $parser->method('parseFromStream')
            ->willReturnCallback(function ($s) use (&$capturedStream): OemFile {
                $capturedStream = $s;

                return $this->makeOemFile();
            });

        new CcsdsClient($fs, $parser)->parseOemFile('test.oem');

        $this->assertSame($stream, $capturedStream);
    }

    private function makeOemFile(): OemFile
    {
        return new OemFile(
            new Header('1.0', new \DateTimeImmutable('2024-01-01T00:00:00'), 'TEST'),
            [],
            [],
        );
    }
}
