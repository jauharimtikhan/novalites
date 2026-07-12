<?php

namespace Novalites\Support;

class Str
{
    /**
     * Cache hasil studly() biar ga compute ulang string yang sama berkali-kali.
     */
    protected static array $studlyCache = [];
    protected static array $camelCache = [];
    protected static array $snakeCache = [];

    // ---------- Case conversion ----------

    public static function studly(string $value): string
    {
        $key = $value;

        if (isset(self::$studlyCache[$key])) {
            return self::$studlyCache[$key];
        }

        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return self::$studlyCache[$key] = str_replace(' ', '', $value);
    }

    public static function camel(string $value): string
    {
        $key = $value;

        if (isset(self::$camelCache[$key])) {
            return self::$camelCache[$key];
        }

        return self::$camelCache[$key] = lcfirst(self::studly($value));
    }

    public static function snake(string $value, string $delimiter = '_'): string
    {
        $key = $value . $delimiter;

        if (isset(self::$snakeCache[$key])) {
            return self::$snakeCache[$key];
        }

        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }

        return self::$snakeCache[$key] = $value;
    }

    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    public static function ucfirst(string $value): string
    {
        return self::upper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
    }

    public static function headline(string $value): string
    {
        $parts = explode(' ', str_replace(['-', '_'], ' ', $value));
        $parts = array_map(fn($p) => self::ucfirst(trim($p)), $parts);
        return implode(' ', array_filter($parts));
    }

    // ---------- Cek isi string ----------

    public static function contains(string $haystack, string|array $needles, bool $ignoreCase = false): bool
    {
        if ($ignoreCase) {
            $haystack = self::lower($haystack);
        }

        foreach ((array) $needles as $needle) {
            $check = $ignoreCase ? self::lower($needle) : $needle;
            if ($needle !== '' && str_contains($haystack, $check)) {
                return true;
            }
        }
        return false;
    }

    public static function containsAll(string $haystack, array $needles, bool $ignoreCase = false): bool
    {
        foreach ($needles as $needle) {
            if (!self::contains($haystack, $needle, $ignoreCase)) {
                return false;
            }
        }
        return true;
    }

    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function is(string $pattern, string $value): bool
    {
        if ($pattern === $value) {
            return true;
        }

        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '\z#u', $value);
    }

    // ---------- Manipulasi ----------

    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }
        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || mb_strlen($value) === mb_strlen($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    public static function slug(string $title, string $separator = '-'): string
    {
        $title = self::lower($title);

        // Ganti karakter non-alphanumeric jadi separator
        $title = preg_replace('/[^a-z0-9' . preg_quote($separator, '/') . ']+/u', $separator, $title);

        // Buang separator dobel & di ujung
        $title = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $title);

        return trim($title, $separator);
    }

    public static function mask(string $value, string $character, int $index, ?int $length = null): string
    {
        if ($character === '') {
            return $value;
        }

        $strlen = mb_strlen($value);

        if ($index < 0) {
            $index = max(0, $strlen + $index);
        }

        $length = $length ?? $strlen - $index;
        $length = min($length, $strlen - $index);

        if ($length <= 0) {
            return $value;
        }

        $start = mb_substr($value, 0, $index);
        $masked = str_repeat($character, $length);
        $end = mb_substr($value, $index + $length);

        return $start . $masked . $end;
    }

    public static function padBoth(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_BOTH);
    }

    public static function padLeft(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_LEFT);
    }

    public static function padRight(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_RIGHT);
    }

    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr_replace($subject, $replace, $position, strlen($search));
    }

    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr_replace($subject, $replace, $position, strlen($search));
    }

    public static function between(string $value, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $value;
        }

        $start = strpos($value, $from);
        if ($start === false) {
            return '';
        }
        $start += strlen($from);

        $end = strpos($value, $to, $start);
        if ($end === false) {
            return '';
        }

        return substr($value, $start, $end - $start);
    }

    public static function remove(string|array $search, string $subject): string
    {
        return str_replace($search, '', $subject);
    }

    public static function reverse(string $value): string
    {
        return implode(array_reverse(mb_str_split($value)));
    }

    public static function squish(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    // ---------- Generator ----------

    public static function random(int $length = 16): string
    {
        return substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length);
    }

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versi 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function ulid(): string
    {
        // Sederhana: timestamp (48 bit) + random (80 bit), encode base32 Crockford
        $time = (int) (microtime(true) * 1000);
        $timeChars = self::encodeBase32(str_pad(self::toBinary($time, 6), 6, "\0", STR_PAD_LEFT));

        $randomBytes = random_bytes(10);
        $randomChars = self::encodeBase32($randomBytes);

        return strtoupper($timeChars . $randomChars);
    }

    protected static function toBinary(int $number, int $length): string
    {
        $binary = '';
        for ($i = 0; $i < $length; $i++) {
            $binary = chr($number & 0xFF) . $binary;
            $number >>= 8;
        }
        return $binary;
    }

    protected static function encodeBase32(string $bytes): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }

    public static function password(int $length = 32): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    // ---------- Pluralization sederhana (bukan full library kayak Doctrine Inflector) ----------

    public static function plural(string $value, int $count = 2): string
    {
        if ($count === 1) {
            return $value;
        }

        // Rule sederhana buat bahasa Inggris, ga selengkap Laravel (yang pakai library terpisah)
        $irregular = [
            'child'  => 'children',
            'person' => 'people',
            'man'    => 'men',
            'woman'  => 'women',
        ];

        if (isset($irregular[self::lower($value)])) {
            return $irregular[self::lower($value)];
        }

        if (preg_match('/(s|ss|sh|ch|x|z)$/i', $value)) {
            return $value . 'es';
        }

        if (preg_match('/[^aeiou]y$/i', $value)) {
            return substr($value, 0, -1) . 'ies';
        }

        return $value . 's';
    }

    // ---------- Cek tipe/format ----------

    public static function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        );
    }

    public static function isJson(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }
}
