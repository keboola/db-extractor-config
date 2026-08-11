<?php

declare(strict_types=1);

namespace Keboola\DbExtractorConfig\Incremental;

use DateTimeImmutable;
use DateTimeZone;
use Keboola\DbExtractorConfig\Exception\InvalidArgumentException;

/**
 * Resolves incremental fetching window bounds (start/end).
 *
 * Window bounds are evaluated in the extractor's (process) timezone, which is UTC in the standard
 * component images. Relative expressions (e.g. "20 minutes ago") are anchored at the injected `$now`.
 * Absolute date strings are interpreted in the extractor timezone, not in `$now`'s timezone, so the
 * resolved instant stays consistent regardless of which timezone the injected clock carries.
 */
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
            // strtotime() parses relative and absolute strings in the extractor's (process) timezone;
            // format the resulting instant in that SAME timezone so absolute and relative bounds agree
            // regardless of the injected clock's timezone.
            return (new DateTimeImmutable('@' . $ts))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d H:i:s');
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
