<?php

declare(strict_types=1);

namespace Keboola\DbExtractorConfig\Tests\ValueObject;

use Keboola\DbExtractorConfig\Configuration\ValueObject\IncrementalFetchingConfig;
use Keboola\DbExtractorConfig\Exception\PropertyNotSetException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class IncrementalFetchingConfigTest extends TestCase
{
    public function testEmpty(): void
    {
        Assert::assertSame(null, IncrementalFetchingConfig::fromArray([]));
    }

    public function testNotEnabled(): void
    {
        Assert::assertSame(null, IncrementalFetchingConfig::fromArray(['incremental' => false]));
    }

    public function testColumn(): void
    {
        /** @var IncrementalFetchingConfig $config */
        $config = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'col123',
        ]);
        Assert::assertSame('col123', $config->getColumn());
        Assert::assertSame(false, $config->hasLimit());
        try {
            $config->getLimit();
        } catch (PropertyNotSetException $e) {
            // ok
        }
    }

    public function testColumnAndLimit(): void
    {
        /** @var IncrementalFetchingConfig $config */
        $config = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'col123',
            'incrementalFetchingLimit' => 456,
        ]);
        Assert::assertSame('col123', $config->getColumn());
        Assert::assertSame(true, $config->hasLimit());
        Assert::assertSame(456, $config->getLimit());
    }

    public function testDefaultsToWatermarkMode(): void
    {
        $cfg = IncrementalFetchingConfig::fromArray(['incrementalFetchingColumn' => 'ts']);
        self::assertNotNull($cfg);
        self::assertSame(IncrementalFetchingConfig::MODE_WATERMARK, $cfg->getMode());
        self::assertFalse($cfg->isWindowMode());
        self::assertFalse($cfg->hasWindow());
        self::assertFalse($cfg->hasLookback());
    }

    public function testWindowRoundTripAndColumnTypeIsImmutable(): void
    {
        $cfg = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingMode' => 'window',
            'incrementalFetchingStart' => '10 days ago',
        ]);
        self::assertNotNull($cfg);
        self::assertTrue($cfg->isWindowMode());
        self::assertTrue($cfg->hasWindow());
        self::assertSame('10 days ago', $cfg->getWindowStart());
        self::assertNull($cfg->getWindowEnd());

        $typed = $cfg->withColumnType('TIMESTAMP');
        self::assertSame('TIMESTAMP', $typed->getColumnType());
        // mode + window preserved through the copy
        self::assertTrue($typed->hasWindow());
        self::assertSame('10 days ago', $typed->getWindowStart());

        // immutability: the original is untouched and still throws
        $this->expectException(PropertyNotSetException::class);
        $cfg->getColumnType();
    }

    public function testNoWindowAndEmptyStringsAreNotAWindow(): void
    {
        $none = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'id',
            'incrementalFetchingMode' => 'window',
        ]);
        self::assertNotNull($none);
        self::assertFalse($none->hasWindow());
        self::assertNull($none->getWindowStart());

        $empty = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'id',
            'incrementalFetchingMode' => 'window',
            'incrementalFetchingStart' => '',
            'incrementalFetchingEnd' => '',
        ]);
        self::assertNotNull($empty);
        self::assertFalse($empty->hasWindow());
        self::assertNull($empty->getWindowStart());
        self::assertNull($empty->getWindowEnd());
    }

    public function testWindowBoundsAreIgnoredInWatermarkMode(): void
    {
        // Leftover window keys (e.g. from raw JSON) are ignored when the mode is not "window".
        $cfg = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingStart' => '10 days ago',
            'incrementalFetchingEnd' => 'now',
        ]);
        self::assertNotNull($cfg);
        self::assertSame(IncrementalFetchingConfig::MODE_WATERMARK, $cfg->getMode());
        self::assertFalse($cfg->hasWindow());
    }

    public function testLookbackRoundTripInWatermarkMode(): void
    {
        $cfg = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingLookback' => '20 minutes',
        ]);
        self::assertNotNull($cfg);
        self::assertFalse($cfg->isWindowMode());
        self::assertTrue($cfg->hasLookback());
        self::assertSame('20 minutes', $cfg->getLookback());

        // lookback preserved through the type copy
        $typed = $cfg->withColumnType('TIMESTAMP');
        self::assertTrue($typed->hasLookback());
        self::assertSame('20 minutes', $typed->getLookback());
    }

    public function testEmptyLookbackIsNotALookback(): void
    {
        $cfg = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingLookback' => '',
        ]);
        self::assertNotNull($cfg);
        self::assertFalse($cfg->hasLookback());
        self::assertNull($cfg->getLookback());
    }

    public function testLookbackIsIgnoredInWindowMode(): void
    {
        // The lookback offset belongs to watermark mode; in window mode it is ignored.
        $cfg = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingMode' => 'window',
            'incrementalFetchingLookback' => '20 minutes',
            'incrementalFetchingStart' => '10 days ago',
        ]);
        self::assertNotNull($cfg);
        self::assertTrue($cfg->isWindowMode());
        self::assertFalse($cfg->hasLookback());
        self::assertTrue($cfg->hasWindow());
    }
}
