<?php

declare(strict_types=1);

namespace Keboola\DbExtractorConfig\Incremental;

use DateTimeImmutable;
use DateTimeZone;
use Keboola\DbExtractorConfig\Exception\InvalidArgumentException;
use Throwable;

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

    /**
     * Lowers a stored watermark by a lookback margin, so a watermark-mode incremental run re-scans a
     * safety window BEHIND where it left off. This catches rows whose incremental value was assigned
     * before commit but became visible only after the watermark had already advanced past it.
     *
     * The lookback is anchored on the watermark itself (not on "now"), so there is no clock dependency,
     * and it always moves STRICTLY BACKWARDS: only the magnitude of the offset is used, so "20 minutes",
     * "20 minutes ago" and "-20 minutes" all mean the same "look back 20 minutes" (no wrong-direction
     * footgun from the "ago" keyword or a stray sign).
     *  - TIMESTAMP: the lookback is a duration (e.g. "20 minutes", "1 hour", "2 days"), subtracted from
     *    the watermark. The result keeps the watermark's own timezone qualification: an offset-qualified
     *    watermark (timestamptz, e.g. "...+00") yields an offset-qualified bound (an absolute instant,
     *    correct under any DB session TimeZone); a naive watermark (timestamp) yields a naive bound.
     *  - INTEGER/NUMERIC/FLOAT: the lookback is a number subtracted from the numeric watermark.
     */
    public function resolveLookbackLowerBound(string $watermark, string $lookback, string $columnType): string
    {
        if ($columnType === self::TIMESTAMP) {
            try {
                $base = new DateTimeImmutable($watermark);
            } catch (Throwable $e) {
                throw new InvalidArgumentException(
                    sprintf('Cannot parse incremental fetching watermark "%s" as a date.', $watermark),
                    0,
                    $e,
                );
            }
            $baseTs = $base->getTimestamp();
            $signed = strtotime($lookback, $baseTs);
            if ($signed === false) {
                throw new InvalidArgumentException(
                    sprintf('Cannot parse incremental fetching lookback "%s" as a duration.', $lookback),
                );
            }
            // Subtract the MAGNITUDE of the offset, so the bound is always behind the watermark.
            $resultTs = $baseTs - abs($signed - $baseTs);
            $result = new DateTimeImmutable('@' . $resultTs);
            $baseTz = $base->getTimezone();
            if ($baseTz !== false) {
                $result = $result->setTimezone($baseTz);
            }

            return $this->watermarkHasOffset($watermark)
                ? $result->format('Y-m-d H:i:sP')
                : $result->format('Y-m-d H:i:s');
        }
        if (in_array($columnType, self::NUMERIC_TYPES, true)) {
            if (!is_numeric($watermark) || !is_numeric($lookback)) {
                throw new InvalidArgumentException(sprintf(
                    'Incremental fetching lookback "%s" and watermark "%s" must be numeric for a %s column.',
                    $lookback,
                    $watermark,
                    $columnType,
                ));
            }
            return $this->subtractNumeric($watermark, $lookback);
        }
        throw new InvalidArgumentException(
            sprintf('Unsupported incremental fetching column type "%s".', $columnType),
        );
    }

    /**
     * True when the watermark string carries an explicit timezone (trailing "Z" or a "+HH[:MM]" /
     * "-HH[:MM]" offset), i.e. it came from a timestamptz column. Naive timestamps have neither.
     */
    private function watermarkHasOffset(string $watermark): bool
    {
        return preg_match('/(?:[zZ]|[+-]\d{2}(?::?\d{2})?)$/', trim($watermark)) === 1;
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

    /**
     * Subtracts one numeric string from another without losing precision on the common cases:
     * exact integer math for auto-increment id columns (the realistic numeric watermark), and
     * bcmath for decimals when the extension is available (a plain float subtraction is only ever
     * reached on a FLOAT column, whose values are approximate by nature anyway).
     *
     * Only the magnitude of the lookback is used, so it always moves strictly backwards (mirrors the
     * timestamp branch): "10" and "-10" both look back 10.
     */
    private function subtractNumeric(string $watermark, string $lookback): string
    {
        $lookback = ltrim($lookback, '+-');
        if (preg_match('/^-?\d+$/', $watermark) === 1 && preg_match('/^\d+$/', $lookback) === 1) {
            if (function_exists('bcsub')) {
                return bcsub($watermark, $lookback, 0);
            }
            return (string) ((int) $watermark - (int) $lookback);
        }
        if (function_exists('bcsub')) {
            $scale = max($this->fractionLength($watermark), $this->fractionLength($lookback));
            return bcsub($watermark, $lookback, $scale);
        }
        return (string) ((float) $watermark - (float) $lookback);
    }

    private function fractionLength(string $number): int
    {
        $dot = strpos($number, '.');
        return $dot === false ? 0 : strlen($number) - $dot - 1;
    }
}
