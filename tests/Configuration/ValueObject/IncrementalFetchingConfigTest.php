<?php

declare(strict_types=1);

namespace Keboola\DbExtractorConfig\Tests\Configuration\ValueObject;

use Keboola\DbExtractorConfig\Configuration\ValueObject\IncrementalFetchingConfig;
use Keboola\DbExtractorConfig\Exception\PropertyNotSetException;
use PHPUnit\Framework\TestCase;

class IncrementalFetchingConfigTest extends TestCase
{
    public function testWindowRoundTripAndColumnTypeIsImmutable(): void
    {
        $cfg = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingStart' => '10 days ago',
        ]);
        self::assertNotNull($cfg);
        self::assertTrue($cfg->hasWindow());
        self::assertSame('10 days ago', $cfg->getWindowStart());
        self::assertNull($cfg->getWindowEnd());

        $typed = $cfg->withColumnType('TIMESTAMP');
        self::assertSame('TIMESTAMP', $typed->getColumnType());

        // immutability: the original is untouched and still throws
        $this->expectException(PropertyNotSetException::class);
        $cfg->getColumnType();
    }

    public function testNoWindowAndEmptyStringsAreNotAWindow(): void
    {
        $none = IncrementalFetchingConfig::fromArray(['incrementalFetchingColumn' => 'id']);
        self::assertNotNull($none);
        self::assertFalse($none->hasWindow());
        self::assertNull($none->getWindowStart());

        $empty = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'id',
            'incrementalFetchingStart' => '',
            'incrementalFetchingEnd' => '',
        ]);
        self::assertNotNull($empty);
        self::assertFalse($empty->hasWindow());
        self::assertNull($empty->getWindowStart());
        self::assertNull($empty->getWindowEnd());
    }

    public function testDisabledWhenNoColumn(): void
    {
        self::assertNull(IncrementalFetchingConfig::fromArray([]));
    }

    public function testLimitStillWorks(): void
    {
        $cfg = IncrementalFetchingConfig::fromArray([
            'incrementalFetchingColumn' => 'id',
            'incrementalFetchingLimit' => 500,
        ]);
        self::assertNotNull($cfg);
        self::assertTrue($cfg->hasLimit());
        self::assertSame(500, $cfg->getLimit());
        self::assertSame('id', $cfg->getColumn());
    }
}
