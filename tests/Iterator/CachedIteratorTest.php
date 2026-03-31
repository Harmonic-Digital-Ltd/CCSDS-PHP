<?php

declare(strict_types=1);

namespace HarmonicDigital\Ccsds\Tests\Iterator;

use HarmonicDigital\Ccsds\Iterator\CachedIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spy around ArrayIterator that counts calls to each Iterator method.
 * Using a spy (real implementation + call counting) is cleaner than a mock
 * here because the iterator methods must behave correctly to drive CachedIterator.
 *
 * @extends \ArrayIterator<array-key, mixed>
 */
final class SpyIterator extends \ArrayIterator
{
    public int $validCalls = 0;
    public int $currentCalls = 0;
    public int $keyCalls = 0;
    public int $nextCalls = 0;
    public int $rewindCalls = 0;

    #[\Override]
    public function valid(): bool
    {
        ++$this->validCalls;

        return parent::valid();
    }

    #[\Override]
    public function current(): mixed
    {
        ++$this->currentCalls;

        return parent::current();
    }

    #[\Override]
    public function key(): string|int|null
    {
        ++$this->keyCalls;

        return parent::key();
    }

    #[\Override]
    public function next(): void
    {
        ++$this->nextCalls;
        parent::next();
    }

    #[\Override]
    public function rewind(): void
    {
        ++$this->rewindCalls;
        parent::rewind();
    }
}

#[CoversClass(CachedIterator::class)]
final class CachedIteratorTest extends TestCase
{
    /**
     * @return array<string, array{array<array-key, string>}>
     */
    public static function keyTypeProvider(): array
    {
        return [
            'list (0-based sequential)' => [['a', 'b', 'c']],
            'numeric non-list (non-zero-based)' => [[5 => 'a', 10 => 'b', 15 => 'c']],
            'string keys' => [['foo' => 'a', 'bar' => 'b', 'baz' => 'c']],
        ];
    }

    #[DataProvider('keyTypeProvider')]
    public function testYieldsCorrectKeysAndValues(array $data): void
    {
        $spy = new SpyIterator($data);
        $cached = new CachedIterator($spy);

        $this->assertSame($data, iterator_to_array($cached));
    }

    #[DataProvider('keyTypeProvider')]
    public function testMultipleFullIterationsReturnIdenticalResults(array $data): void
    {
        $spy = new SpyIterator($data);
        $cached = new CachedIterator($spy);

        $first = iterator_to_array($cached);
        $second = iterator_to_array($cached);
        $third = iterator_to_array($cached);

        $this->assertSame($data, $first);
        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
    }

    #[DataProvider('keyTypeProvider')]
    public function testInnerIteratorNotAccessedAfterExhaustion(array $data): void
    {
        $spy = new SpyIterator($data);
        $cached = new CachedIterator($spy);

        iterator_to_array($cached);

        $validAfterFirst = $spy->validCalls;
        $currentAfterFirst = $spy->currentCalls;
        $keyAfterFirst = $spy->keyCalls;
        $nextAfterFirst = $spy->nextCalls;

        // Two more complete passes — must not touch the spy at all.
        iterator_to_array($cached);
        iterator_to_array($cached);

        $this->assertSame($validAfterFirst, $spy->validCalls, 'valid() must not be called again after exhaustion');
        $this->assertSame($currentAfterFirst, $spy->currentCalls, 'current() must not be called again after exhaustion');
        $this->assertSame($keyAfterFirst, $spy->keyCalls, 'key() must not be called again after exhaustion');
        $this->assertSame($nextAfterFirst, $spy->nextCalls, 'next() must not be called again after exhaustion');
    }

    #[DataProvider('keyTypeProvider')]
    public function testEarlyBreakThenFullIterationOnlyAccessesUncachedItems(array $data): void
    {
        $spy = new SpyIterator($data);
        $cached = new CachedIterator($spy);

        // Abandon after the very first item is yielded.
        foreach ($cached as $_) {
            break;
        }

        $currentAfterBreak = $spy->currentCalls;

        $result = iterator_to_array($cached);

        $uncached = count($data) - 1;
        $this->assertSame(
            $currentAfterBreak + $uncached,
            $spy->currentCalls,
            'current() must only be called for items not already in the cache',
        );
        $this->assertSame($data, $result, 'Result must be the full dataset starting from key 0');
    }

    public function testCachedObjectsAreIdenticalInstancesAcrossIterations(): void
    {
        $dates = ['2024-01-01', '2024-06-15', '2024-12-31'];

        $generator = (static function () use ($dates): \Generator {
            foreach ($dates as $i => $date) {
                yield $i => new \DateTimeImmutable($date);
            }
        })();

        $cached = new CachedIterator($generator);

        $first = iterator_to_array($cached);
        $second = iterator_to_array($cached);

        $this->assertSame($first, $second);

    }

    #[DataProvider('keyTypeProvider')]
    public function testRewindIsNeverCalledOnInnerIterator(array $data): void
    {
        $spy = new SpyIterator($data);
        $cached = new CachedIterator($spy);

        $result1 = iterator_to_array($cached);
        $result2 = iterator_to_array($cached);
        $this->assertSame($result1, $result2);

        $this->assertSame(0, $spy->rewindCalls, 'CachedIterator must never call rewind() on the inner iterator');
    }
}
