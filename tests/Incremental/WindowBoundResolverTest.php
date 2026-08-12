<?php

declare(strict_types=1);

namespace Keboola\DbExtractorConfig\Tests\Incremental;

use DateTimeImmutable;
use Keboola\DbExtractorConfig\Exception\InvalidArgumentException;
use Keboola\DbExtractorConfig\Incremental\WindowBoundResolver;
use PHPUnit\Framework\TestCase;

class WindowBoundResolverTest extends TestCase
{
    public function testTimestampStartResolvesRelativeAndAbsolute(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        $r = new WindowBoundResolver();
        self::assertSame('2026-08-01 12:00:00', $r->resolveLowerBound('10 days ago', 'TIMESTAMP', $now));
        self::assertSame('2026-01-01 00:00:00', $r->resolveLowerBound('2026-01-01', 'TIMESTAMP', $now));
        self::assertNull($r->resolveLowerBound(null, 'TIMESTAMP', $now));
    }

    public function testTimestampSubHourRelativeLowerAndNowUpperBound(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        $r = new WindowBoundResolver();
        self::assertSame('2026-08-11 11:40:00', $r->resolveLowerBound('20 minutes ago', 'TIMESTAMP', $now));
        self::assertSame('2026-08-11 12:00:00', $r->resolveUpperBound('now', 'TIMESTAMP', $now));
    }

    public function testTimestampEndResolvesRelativeAndAbsolute(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        $r = new WindowBoundResolver();
        self::assertSame('2026-08-11 11:00:00', $r->resolveUpperBound('1 hour ago', 'TIMESTAMP', $now));
        self::assertSame('2026-06-01 00:00:00', $r->resolveUpperBound('2026-06-01', 'TIMESTAMP', $now));
    }

    public function testNumericBoundsPassThroughAsIs(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        $r = new WindowBoundResolver();
        self::assertSame('50000', $r->resolveLowerBound('50000', 'INTEGER', $now));
        self::assertSame('9999', $r->resolveUpperBound('9999', 'NUMERIC', $now));
    }

    public function testNumericColumnRejectsNonNumericValue(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        $r = new WindowBoundResolver();
        $this->expectException(InvalidArgumentException::class);
        $r->resolveLowerBound('10 days ago', 'INTEGER', $now);
    }

    public function testUnparseableDateThrows(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        $r = new WindowBoundResolver();
        $this->expectException(InvalidArgumentException::class);
        $r->resolveLowerBound('not a date', 'TIMESTAMP', $now);
    }

    public function testUnknownColumnTypeThrows(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        $r = new WindowBoundResolver();
        $this->expectException(InvalidArgumentException::class);
        $r->resolveLowerBound('1', 'BOOLEAN', $now);
    }

    public function testEmptyUpperBoundResolvesToNull(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        $r = new WindowBoundResolver();
        self::assertNull($r->resolveUpperBound('', 'TIMESTAMP', $now));
    }

    public function testLookbackTimestampSubtractsDurationFromWatermark(): void
    {
        $r = new WindowBoundResolver();
        // pure wall-clock shift on the watermark, no "now" involved
        self::assertSame(
            '2026-08-11 11:40:00',
            $r->resolveLookbackLowerBound('2026-08-11 12:00:00', '20 minutes', 'TIMESTAMP'),
        );
        self::assertSame(
            '2026-08-10 12:00:00',
            $r->resolveLookbackLowerBound('2026-08-11 12:00:00', '1 day', 'TIMESTAMP'),
        );
    }

    public function testLookbackNumericIntegerIsExact(): void
    {
        $r = new WindowBoundResolver();
        self::assertSame('9990', $r->resolveLookbackLowerBound('10000', '10', 'INTEGER'));
        // exact well beyond float53 precision
        self::assertSame(
            '9007199254740983',
            $r->resolveLookbackLowerBound('9007199254740993', '10', 'INTEGER'),
        );
    }

    public function testLookbackNumericDecimal(): void
    {
        $r = new WindowBoundResolver();
        // Value equality, tolerant of bcmath ("99.50") vs. float-fallback ("99.5") formatting.
        self::assertSame(99.5, (float) $r->resolveLookbackLowerBound('100.00', '0.50', 'NUMERIC'));
    }

    public function testLookbackUnparseableDurationThrows(): void
    {
        $r = new WindowBoundResolver();
        $this->expectException(InvalidArgumentException::class);
        $r->resolveLookbackLowerBound('2026-08-11 12:00:00', '20 bananas', 'TIMESTAMP');
    }

    public function testLookbackNonNumericValueThrowsForNumericColumn(): void
    {
        $r = new WindowBoundResolver();
        $this->expectException(InvalidArgumentException::class);
        $r->resolveLookbackLowerBound('100', '20 minutes', 'INTEGER');
    }

    public function testLookbackUnknownColumnTypeThrows(): void
    {
        $r = new WindowBoundResolver();
        $this->expectException(InvalidArgumentException::class);
        $r->resolveLookbackLowerBound('1', '1', 'BOOLEAN');
    }
}
