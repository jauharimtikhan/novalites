<?php

namespace Novalites\Auth;

use Novalites\Session\Session;

class Auth
{
    protected const SESSION_KEY = '_user_id';

    protected static ?string $userModel = null;
    protected static mixed $resolvedUser = null;
    protected static bool $resolved = false;

    /**
     * Daftarin Model User yang dipakai (dipanggil sekali di bootstrap).
     */
    public static function useModel(string $modelClass): void
    {
        self::$userModel = $modelClass;
    }

    /**
     * Login user — simpan user_id ke session.
     */
    public static function login(mixed $user): void
    {
        Session::put(self::SESSION_KEY, $user->id);
        Session::regenerate(); // WAJIB, mencegah session fixation attack pas login

        self::$resolvedUser = $user;
        self::$resolved = true;
    }

    /**
     * Login pakai ID langsung (misal abis verifikasi token).
     */
    public static function loginUsingId(mixed $id): mixed
    {
        $user = self::resolveModel()::find($id);

        if ($user === null) {
            return null;
        }

        self::login($user);
        return $user;
    }

    /**
     * Cek kredensial (email + password) lalu login kalau cocok.
     */
    public static function attempt(array $credentials): bool
    {
        $model = self::resolveModel();

        $user = $model::where('email', $credentials['email'] ?? '')->first();

        if ($user === null) {
            return false;
        }

        if (!password_verify($credentials['password'] ?? '', $user->password)) {
            return false;
        }

        self::login($user);
        return true;
    }

    /**
     * Logout — hapus session user, regenerate ID.
     */
    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::regenerate();

        self::$resolvedUser = null;
        self::$resolved = true;
    }

    /**
     * Ambil user yang lagi login. null kalau belum login.
     */
    public static function user(): mixed
    {
        if (self::$resolved) {
            return self::$resolvedUser;
        }

        $id = Session::get(self::SESSION_KEY);

        if ($id === null) {
            self::$resolved = true;
            return null;
        }

        self::$resolvedUser = self::resolveModel()::find($id);
        self::$resolved = true;

        return self::$resolvedUser;
    }

    public static function id(): mixed
    {
        return self::user()?->id;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    protected static function resolveModel(): string
    {
        if (self::$userModel === null) {
            throw new \RuntimeException(
                'Model User belum didaftarkan. Panggil Auth::useModel(User::class) di bootstrap.'
            );
        }
        return self::$userModel;
    }
}
