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
}
