<?php

namespace Novalites\Security;

use Novalites\Session\Session;

class CsrfToken
{
    protected const SESSION_KEY = '_csrf_token';

    /**
     * Ambil token yang aktif. Kalau belum ada, generate baru.
     */
    public static function get(): string
    {
        if (!Session::has(self::SESSION_KEY)) {
            self::regenerate();
        }

        return Session::get(self::SESSION_KEY);
    }

    /**
     * Generate token baru, replace yang lama.
     */
    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::put(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * Verifikasi token yang dikirim user cocok sama yang di session.
     * Pakai hash_equals() buat mencegah timing attack.
     */
    public static function verify(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $sessionToken = Session::get(self::SESSION_KEY);

        if ($sessionToken === null) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Generate hidden input field siap pakai di form HTML.
     */
    public static function field(): string
    {
        $token = self::get();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Buat dipakai di header AJAX (meta tag).
     */
    public static function metaTag(): string
    {
        $token = self::get();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
