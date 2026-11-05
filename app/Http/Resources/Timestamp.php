<?php

namespace App\Http\Resources;

use Carbon\CarbonInterface;

/**
 * Timestamps are stored in UTC and returned as full ISO-8601 datetimes in the business timezone
 * (Asia/Kuala_Lumpur, GMT+8), so clients always get an explicit offset, e.g. 2026-11-05T10:15:00+08:00.
 */
final class Timestamp
{
    public const DISPLAY_TIMEZONE = 'Asia/Kuala_Lumpur';

    public static function format(?CarbonInterface $value): ?string
    {
        return $value?->copy()->setTimezone(self::DISPLAY_TIMEZONE)->toIso8601String();
    }
}

