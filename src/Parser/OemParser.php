<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds\Parser;

use HarmonicDigital\Ccsds\Exception\ParseException;
use HarmonicDigital\Ccsds\Iterator\CachedIterator;
use HarmonicDigital\Ccsds\Oem\Header;
use HarmonicDigital\Ccsds\Oem\Metadata;
use HarmonicDigital\Ccsds\Oem\OemFile;
use HarmonicDigital\Ccsds\Oem\OemSegment;
use HarmonicDigital\Ccsds\Oem\StateVector;

/** @api */
final class OemParser implements OemParserInterface
{
    /**
     * @param resource $stream
     *
     * @throws ParseException
     */
    #[\Override]
    public function parseFromStream($stream): OemFile
    {
        try {
            [$header, $comments, $pendingLine] = $this->readHeader($stream);
        } catch (\Throwable $e) {
            fclose($stream);
            throw $e;
        }

        return new OemFile($header, $comments, new CachedIterator($this->generateSegments($stream, $pendingLine)));
    }

    /**
     * @param resource $stream
     *
     * @throws ParseException
     *
     * @return array{Header, string[], string|null}
     */
    private function readHeader($stream): array
    {
        $version = null;
        $creationDate = null;
        $originator = null;
        $comments = [];
        $lineNumber = 0;
        $pendingLine = null;

        while (($line = fgets($stream)) !== false) {
            $lineNumber++;
            $trimmed = trim($line);

            if ('' === $trimmed) {
                continue;
            }

            if ('META_START' === $trimmed) {
                $pendingLine = $trimmed;
                break;
            }

            if (str_starts_with($trimmed, 'CCSDS_OEM_VERS')) {
                [, $version] = $this->parseKeyValue($trimmed, $lineNumber);
            } elseif (str_starts_with($trimmed, 'CREATION_DATE')) {
                [, $dateStr] = $this->parseKeyValue($trimmed, $lineNumber);
                $creationDate = $this->parseDateTime($dateStr, $lineNumber);
            } elseif (str_starts_with($trimmed, 'ORIGINATOR')) {
                [, $originator] = $this->parseKeyValue($trimmed, $lineNumber);
            } elseif (str_starts_with($trimmed, 'COMMENT')) {
                $comments[] = ltrim(substr($trimmed, 7));
            } else {
                throw new ParseException(sprintf('Unexpected content in header on line %d: %s', $lineNumber, $trimmed));
            }
        }

        if (null === $version || null === $creationDate || null === $originator) {
            throw new ParseException('Missing required header fields: CCSDS_OEM_VERS, CREATION_DATE, ORIGINATOR');
        }

        return [new Header($version, $creationDate, $originator), $comments, $pendingLine];
    }

    /**
     * @param resource    $stream      Ownership is transferred; the generator closes it when done.
     * @param string|null $pendingLine A line already consumed by readHeader that the generator must process first.
     * @return \Generator<int, OemSegment>
     */
    private function generateSegments($stream, ?string $pendingLine): \Generator
    {
        $inMeta = false;
        $inCovariance = false;
        $metaFields = [];
        $currentMetadata = null;
        $currentStateVectors = [];
        $lineNumber = 0;

        try {
            if ('META_START' === $pendingLine) {
                $inMeta = true;
                $metaFields = [];
                $currentStateVectors = [];
            }

            while (($line = fgets($stream)) !== false) {
                $lineNumber++;
                $trimmed = trim($line);

                if ('' === $trimmed) {
                    continue;
                }

                if ($inCovariance) {
                    if ('COVARIANCE_STOP' === $trimmed) {
                        $inCovariance = false;
                    }
                    continue;
                }

                if ($inMeta) {
                    if ('META_STOP' === $trimmed) {
                        $currentMetadata = $this->buildMetadata($metaFields, $lineNumber);
                        $inMeta = false;
                    } else {
                        [$key, $value] = $this->parseKeyValue($trimmed, $lineNumber);
                        $metaFields[$key] = $value;
                    }
                    continue;
                }

                if ('META_START' === $trimmed) {
                    if (null !== $currentMetadata && [] !== $currentStateVectors) {
                        yield new OemSegment($currentMetadata, $currentStateVectors);
                    }
                    $currentMetadata = null;
                    $currentStateVectors = [];
                    $metaFields = [];
                    $inMeta = true;
                    continue;
                }

                if ('COVARIANCE_START' === $trimmed) {
                    $inCovariance = true;
                    continue;
                }

                if (str_starts_with($trimmed, 'COMMENT')) {
                    continue;
                }

                if (null !== $currentMetadata) {
                    $currentStateVectors[] = $this->parseStateVector($trimmed, $lineNumber);
                }
            }

            if (null !== $currentMetadata && [] !== $currentStateVectors) {
                yield new OemSegment($currentMetadata, $currentStateVectors);
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return array{string, string} The key and value */
    private function parseKeyValue(string $line, int $lineNumber): array
    {
        $parts = explode('=', $line, 2);
        if (2 !== count($parts)) {
            throw new ParseException(sprintf('Expected key=value on line %d: %s', $lineNumber, $line));
        }

        return [trim($parts[0]), trim($parts[1])];
    }

    /** @param array<string, string> $fields */
    private function buildMetadata(array $fields, int $lineNumber): Metadata
    {
        $require = fn(string $key): string => $fields[$key]
            ?? throw new ParseException(sprintf('Missing required metadata field %s near line %d', $key, $lineNumber));

        return new Metadata(
            $require('OBJECT_NAME'),
            $require('OBJECT_ID'),
            $require('CENTER_NAME'),
            $require('REF_FRAME'),
            $require('TIME_SYSTEM'),
            $this->parseDateTime($require('START_TIME'), $lineNumber),
            $this->parseDateTime($require('STOP_TIME'), $lineNumber),
            $fields['REF_FRAME_EPOCH'] ?? null,
            $this->parseDateTimeOrNull($fields['USEABLE_START_TIME'] ?? null, $lineNumber),
            $this->parseDateTimeOrNull($fields['USEABLE_STOP_TIME'] ?? null, $lineNumber),
            $fields['INTERPOLATION'] ?? null,
            isset($fields['INTERPOLATION_DEGREE'])
                ? (int) $fields['INTERPOLATION_DEGREE']
                : null,
        );
    }

    private function parseStateVector(string $line, int $lineNumber): StateVector
    {
        /** @var list{string, numeric-string, numeric-string, numeric-string, numeric-string, numeric-string, numeric-string} $tokens */
        $tokens = preg_split('/\s+/', $line);

        return new StateVector(
            $this->parseDateTime($tokens[0], $lineNumber),
            $tokens[1],
            $tokens[2],
            $tokens[3],
            $tokens[4],
            $tokens[5],
            $tokens[6],
        );
    }

    private function parseDateTime(string $value, int $lineNumber): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\DateMalformedStringException $e) {
            throw new ParseException(sprintf('Cannot parse datetime "%s" on line %d', $value, $lineNumber), $e->getCode(), $e);
        }
    }

    private function parseDateTimeOrNull(?string $value, int $lineNumber): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        return $this->parseDateTime($value, $lineNumber);
    }
}
