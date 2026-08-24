<?php

declare(strict_types=1);

namespace Keboola\DbExtractorConfig\Configuration\ValueObject;

use Keboola\DbExtractorConfig\Exception\InvalidArgumentException;
use Keboola\DbExtractorConfig\Exception\PropertyNotSetException;

class IncrementalFetchingConfig implements ValueObject
{
    /** Default mode: resume from the stored watermark (col >= lastFetchedRow), optionally lowered by a lookback. */
    public const MODE_WATERMARK = 'watermark';

    /** Absolute/relative range mode: col >= start [AND col <= end], ignoring the stored watermark. */
    public const MODE_WINDOW = 'window';

    private string $column;

    private ?int $limit;

    private string $mode;

    private ?string $lookback;

    private ?string $windowStart;

    private ?string $windowEnd;

    private ?string $columnType;

    public static function fromArray(array $data): ?self
    {
        // Enabled ?
        if (empty($data['incrementalFetchingColumn'])) {
            return null;
        }

        return new self(
            $data['incrementalFetchingColumn'],
            $data['incrementalFetchingLimit'] ?? null,
            $data['incrementalFetchingMode'] ?? self::MODE_WATERMARK,
            $data['incrementalFetchingLookback'] ?? null,
            $data['incrementalFetchingStart'] ?? null,
            $data['incrementalFetchingEnd'] ?? null,
        );
    }

    public function __construct(
        string $column,
        ?int $limit,
        string $mode = self::MODE_WATERMARK,
        ?string $lookback = null,
        ?string $windowStart = null,
        ?string $windowEnd = null,
        ?string $columnType = null,
    ) {
        $this->column = $column;
        $this->limit = $limit;
        $this->mode = $mode;
        // Normalize empty strings to null so "" is never treated as a lookback offset or a window bound.
        $this->lookback = ($lookback === '') ? null : $lookback;
        $this->windowStart = ($windowStart === '') ? null : $windowStart;
        $this->windowEnd = ($windowEnd === '') ? null : $windowEnd;
        $this->columnType = $columnType;
    }

    public function withColumnType(string $columnType): self
    {
        return new self(
            $this->column,
            $this->limit,
            $this->mode,
            $this->lookback,
            $this->windowStart,
            $this->windowEnd,
            $columnType,
        );
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function hasLimit(): bool
    {
        return $this->limit !== null;
    }

    public function getLimit(): int
    {
        if ($this->limit === null) {
            throw new PropertyNotSetException('Property "limit" is not set.');
        }

        return $this->limit;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isWindowMode(): bool
    {
        return $this->mode === self::MODE_WINDOW;
    }

    public function getLookback(): ?string
    {
        return $this->lookback;
    }

    public function hasLookback(): bool
    {
        // The lookback offset only applies in the (default) watermark mode; it is ignored in window mode.
        return !$this->isWindowMode() && $this->lookback !== null;
    }

    public function getWindowStart(): ?string
    {
        return $this->windowStart;
    }

    public function getWindowEnd(): ?string
    {
        return $this->windowEnd;
    }

    public function hasWindow(): bool
    {
        // Window bounds only apply in window mode; they are ignored in the (default) watermark mode.
        return $this->isWindowMode() && ($this->windowStart !== null || $this->windowEnd !== null);
    }

    public function getColumnType(): string
    {
        if ($this->columnType === null) {
            throw new PropertyNotSetException('Property "columnType" is not set.');
        }

        return $this->columnType;
    }
}
