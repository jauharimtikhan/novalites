<?php

namespace Novalites\Schedule;

class CronExpression
{
    /**
     * Cek apakah cron expression cocok dengan waktu tertentu.
     * Format: menit jam tanggal bulan hari-minggu
     */
    public static function isDue(string $expression, int $timestamp): bool
    {
        $parts = preg_split('/\s+/', trim($expression));

        if (count($parts) !== 5) {
            throw new \InvalidArgumentException("Cron expression tidak valid: {$expression}");
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        $date = [
            'minute'  => (int) date('i', $timestamp),
            'hour'    => (int) date('G', $timestamp),
            'day'     => (int) date('j', $timestamp),
            'month'   => (int) date('n', $timestamp),
            'weekday' => (int) date('w', $timestamp), // 0 = Minggu
        ];

        return self::matches($minute, $date['minute'], 0, 59)
            && self::matches($hour, $date['hour'], 0, 23)
            && self::matches($day, $date['day'], 1, 31)
            && self::matches($month, $date['month'], 1, 12)
            && self::matches($weekday, $date['weekday'], 0, 6);
    }

    protected static function matches(string $field, int $value, int $min, int $max): bool
    {
        if ($field === '*') {
            return true;
        }

        // Support comma: "1,15,30"
        if (str_contains($field, ',')) {
            foreach (explode(',', $field) as $part) {
                if (self::matches($part, $value, $min, $max)) {
                    return true;
                }
            }
            return false;
        }

        // Support step: "*/5" atau "0-30/5"
        if (str_contains($field, '/')) {
            [$range, $step] = explode('/', $field);
            $step = (int) $step;

            [$rangeMin, $rangeMax] = $range === '*'
                ? [$min, $max]
                : array_map('intval', explode('-', $range . '-' . $range));

            for ($i = $rangeMin; $i <= $rangeMax; $i += $step) {
                if ($i === $value) {
                    return true;
                }
            }
            return false;
        }

        // Support range: "1-5"
        if (str_contains($field, '-')) {
            [$rangeMin, $rangeMax] = array_map('intval', explode('-', $field));
            return $value >= $rangeMin && $value <= $rangeMax;
        }

        // Angka biasa
        return (int) $field === $value;
    }
}
