<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds\Tests\Parser;

use HarmonicDigital\Ccsds\Exception\ParseException;
use HarmonicDigital\Ccsds\Oem\Header;
use HarmonicDigital\Ccsds\Oem\Metadata;
use HarmonicDigital\Ccsds\Oem\OemFile;
use HarmonicDigital\Ccsds\Oem\OemSegment;
use HarmonicDigital\Ccsds\Oem\StateVector;
use HarmonicDigital\Ccsds\Parser\OemParser;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OemParser::class)]
#[CoversClass(OemFile::class)]
#[CoversClass(OemSegment::class)]
#[CoversClass(Header::class)]
#[CoversClass(ParseException::class)]
#[CoversClass(Metadata::class)]
#[CoversClass(StateVector::class)]
final class OemParserTest extends TestCase
{
    private OemParser $parser;
    private FilesystemOperator $fs;

    /** @var resource */
    private $resource;

    #[\Override]
    protected function setUp(): void
    {
        $this->parser = new OemParser();
        $this->fs = new Filesystem(new LocalFilesystemAdapter(__DIR__ . '/../data'));
        $this->resource = $this->fs->readStream('H20090909_0001.LOE');
    }

    public function testParseHeader(): void
    {
        $file = $this->parser->parseFromStream($this->resource);

        $this->assertSame('1.0', $file->header->version);
        $this->assertSame('ESOC', $file->header->originator);
        $this->assertSame(
            '2009-09-09T13:20:11',
            $file->header->creationDate->format('Y-m-d\TH:i:s'),
        );
    }

    public function testParseHeaderDoesNotReadSegments(): void
    {
        $file = $this->parser->parseFromStream($this->resource);

        // Accessing header must not trigger any segment/state-vector loading.
        $this->assertSame('1.0', $file->header->version);
        $this->assertCount(2, $file->comments);
    }

    public function testParseComments(): void
    {
        $file = $this->parser->parseFromStream($this->resource);

        $this->assertCount(2, $file->comments);
        $this->assertStringContainsString('DE-405', $file->comments[0]);
        $this->assertSame('HERSCHEL LEOP', $file->comments[1]);
    }

    public function testSegmentCount(): void
    {
        $file = $this->parser->parseFromStream($this->resource);

        $segments = iterator_to_array($file);
        $this->assertCount(2045, $segments);
    }

    public function testFirstSegmentMetadata(): void
    {
        $file = $this->parser->parseFromStream($this->resource);

        $first = $this->firstSegment($file);
        $meta = $first->metadata;

        $this->assertSame('HERSCHEL', $meta->objectName);
        $this->assertSame('2009-026A', $meta->objectId);
        $this->assertSame('EARTH', $meta->centerName);
        $this->assertSame('EME2000', $meta->refFrame);
        $this->assertSame('TDB', $meta->timeSystem);
        $this->assertSame('LAGRANGE', $meta->interpolation);
        $this->assertSame(8, $meta->interpolationDegree);
        $this->assertNull($meta->refFrameEpoch);
        $this->assertNull($meta->useableStartTime);
        $this->assertNull($meta->useableStopTime);
        $this->assertSame('2009-05-14T13:39:04.723000', $meta->startTime->format('Y-m-d\TH:i:s.u'));
        $this->assertSame('2009-05-14T13:51:07.883500', $meta->stopTime->format('Y-m-d\TH:i:s.u'));
    }

    public function testFirstSegmentFirstStateVector(): void
    {
        $file = $this->parser->parseFromStream($this->resource);

        $vectors = $this->firstSegment($file)->stateVectors;
        $sv = $vectors[0];

        $this->assertSame('2009-05-14T13:39:04.723000', $sv->epoch->format('Y-m-d\TH:i:s.u'));
        $this->assertSame('-2729.993178', $sv->x);
        $this->assertSame('7000.165532', $sv->y);
        $this->assertSame('-287.955387', $sv->z);
        $this->assertSame('-10.206409', $sv->xDot);
        $this->assertSame('-0.282463', $sv->yDot);
        $this->assertSame('-1.061592', $sv->zDot);
    }

    public function testLastSegmentLastStateVector(): void
    {
        $file = $this->parser->parseFromStream($this->resource);

        $lastSegment = null;
        foreach ($file as $segment) {
            $lastSegment = $segment;
        }
        $this->assertNotNull($lastSegment);

        $lastKey = array_key_last($lastSegment->stateVectors);
        $this->assertNotNull($lastKey);
        $sv = $lastSegment->stateVectors[$lastKey];

        $this->assertSame('2014-02-07T04:13:37.475564', $sv->epoch->format('Y-m-d\TH:i:s.u'));
        $this->assertSame('-873961.995024', $sv->x);
    }

    public function testMissingHeaderThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('/Missing required header fields/');

        $tmpPath = tempnam(sys_get_temp_dir(), 'oem_test_');
        if (false === $tmpPath) {
            self::fail('Could not create temporary file');
        }

        file_put_contents($tmpPath, "CCSDS_OEM_VERS = 1.0\n");
        $fs = new Filesystem(new LocalFilesystemAdapter(sys_get_temp_dir()));

        try {
            $this->parser->parseFromStream($fs->readStream(basename($tmpPath)));
        } finally {
            unlink($tmpPath);
        }
    }

    public function testMultipleIterationsReturnSameResults(): void
    {
        $file = $this->parser->parseFromStream($this->resource);

        $first = iterator_to_array($file);
        $second = iterator_to_array($file);

        $this->assertCount(2045, $first);
        $this->assertSame($first[0], $second[0]);
        $this->assertSame($first[2044], $second[2044]);
    }

    public function testEarlyExitThenFullIterationStartsFromBeginning(): void
    {
        $file = $this->parser->parseFromStream($this->resource);

        foreach ($file as $segment) {
            break; // abandon after first segment
        }

        $segments = iterator_to_array($file);
        $this->assertCount(2045, $segments);
        $this->assertSame('2009-05-14T13:39:04.723000', $segments[0]->metadata->startTime->format('Y-m-d\TH:i:s.u'));
    }

    private function firstSegment(OemFile $file): OemSegment
    {
        foreach ($file as $segment) {
            return $segment;
        }
        $this->fail('No segments found');
    }
}
