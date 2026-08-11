<?php

declare(strict_types=1);

namespace Keboola\DbExtractorConfig\Incremental;

use DateTimeImmutable;
use Keboola\DbExtractorConfig\Exception\InvalidArgumentException;

class WindowBoundResolver
{
    private const TIMESTAMP = 'TIMESTAMP';
    private const NUMERIC_TYPES = ['INTEGER', 'NUMERIC', 'FLOAT'];

    public function resolveLowerBound(?string $rawStart, string $columnType, DateTimeImmutable $now): ?string
    {
        return $this->resolveBound($rawStart, $columnType, $now);
    }

    public function resolveUpperBound(?string $rawEnd, string $columnType, DateTimeImmutable $now): ?string
    {
        return $this->resolveBound($rawEnd, $columnType, $now);
    }

    private function resolveBound(?string $raw, string $columnType, DateTimeImmutable $now): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if ($columnType === self::TIMESTAMP) {
            $ts = strtotime($raw, $now->getTimestamp());
            if ($ts === false) {
                throw new InvalidArgumentException(
                    sprintf('Cannot parse incremental fetching window value "%s" as a date.', $raw),
                );
            }
            return (new DateTimeImmutable('@' . $ts))->setTimezone($now->getTimezone())->format('Y-m-d H:i:s');
        }
        if (in_array($columnType, self::NUMERIC_TYPES, true)) {
            if (!is_numeric($raw)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Incremental fetching window value "%s" must be numeric for a %s column.',
                        $raw,
                        $columnType,
                    ),
                );
            }
            return $raw;
        }
        throw new InvalidArgumentException(
            sprintf('Unsupported incremental fetching column type "%s".', $columnType),
        );
    }
}
