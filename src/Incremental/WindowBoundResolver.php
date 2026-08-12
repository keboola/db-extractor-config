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
     * The lookback is anchored on the watermark itself (not on "now"), so there is no timezone or clock
     * dependency:
     *  - TIMESTAMP: the lookback is a positive duration (e.g. "20 minutes", "1 hour", "2 days"); it is
     *    subtracted from the watermark as a pure wall-clock shift, evaluated in UTC so DST never distorts
     *    it and the process timezone is irrelevant.
     *  - INTEGER/NUMERIC/FLOAT: the lookback is a positive number subtracted from the numeric watermark.
     */
    public function resolveLookbackLowerBound(string $watermark, string $lookback, string $columnType): string
    {
        if ($columnType === self::TIMESTAMP) {
            try {
                $baseTs = (new DateTimeImmutable($watermark, new DateTimeZone('UTC')))->getTimestamp();
            } catch (Throwable $e) {
                throw new InvalidArgumentException(
                    sprintf('Cannot parse incremental fetching watermark "%s" as a date.', $watermark),
                    0,
                    $e,
                );
            }
            $ts = strtotime('-' . $lookback, $baseTs);
            if ($ts === false) {
                throw new InvalidArgumentException(
                    sprintf('Cannot apply incremental fetching lookback "%s" to the watermark.', $lookback),
                );
            }
            return (new DateTimeImmutable('@' . $ts))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
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
     */
    private function subtractNumeric(string $watermark, string $lookback): string
    {
        if (preg_match('/^-?\d+$/', $watermark) === 1 && preg_match('/^-?\d+$/', $lookback) === 1) {
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
