<?php

declare(strict_types=1);

namespace Keboola\DbExtractorConfig\Configuration\ValueObject;

use Keboola\DbExtractorConfig\Exception\InvalidArgumentException;
use Keboola\DbExtractorConfig\Exception\PropertyNotSetException;

class IncrementalFetchingConfig implements ValueObject
{
    private string $column;

    private ?int $limit;

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
            $data['incrementalFetchingStart'] ?? null,
            $data['incrementalFetchingEnd'] ?? null,
        );
    }

    public function __construct(
        string $column,
        ?int $limit,
        ?string $windowStart = null,
        ?string $windowEnd = null,
        ?string $columnType = null,
    ) {
        $this->column = $column;
        $this->limit = $limit;
        // Normalize empty strings to null so "" is never treated as a window bound.
        $this->windowStart = ($windowStart === '') ? null : $windowStart;
        $this->windowEnd = ($windowEnd === '') ? null : $windowEnd;
        $this->columnType = $columnType;
    }

    public function withColumnType(string $columnType): self
    {
        return new self($this->column, $this->limit, $this->windowStart, $this->windowEnd, $columnType);
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
        return $this->windowStart !== null || $this->windowEnd !== null;
    }

    public function getColumnType(): string
    {
        if ($this->columnType === null) {
            throw new PropertyNotSetException('Property "columnType" is not set.');
        }

        return $this->columnType;
    }
}
