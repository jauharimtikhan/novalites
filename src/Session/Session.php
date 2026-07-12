<?php

namespace Novalites\Session;

class Session
{
    protected static ?SessionHandlerContract $handler = null;

    private function __construct() {}

    public static function driver(): SessionHandlerContract
    {
        if (self::$handler === null) {
            self::$handler = self::resolveDriver();
            self::$handler->start();
        }
        return self::$handler;
    }

    protected static function resolveDriver(): SessionHandlerContract
    {
        $config = require BASE_PATH . '/config/session.php';

        return match ($config['driver']) {
            'database' => new DatabaseSessionHandler($config),
            'file'     => new FileSessionHandler($config),
            default    => throw new \InvalidArgumentException("Session driver '{$config['driver']}' ga dikenali."),
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::driver()->get($key, $default);
    }

    public static function put(string $key, mixed $value): void
    {
        self::driver()->put($key, $value);
    }

    public static function has(string $key): bool
    {
        return self::driver()->has($key);
    }

    public static function forget(string $key): void
    {
        self::driver()->forget($key);
    }

    public static function all(): array
    {
        return self::driver()->all();
    }

    public static function flush(): void
    {
        self::driver()->flush();
    }

    public static function regenerate(): void
    {
        self::driver()->regenerate();
    }

    public static function getId(): string
    {
        return self::driver()->getId();
    }

    /**
     * Ambil sekali lalu langsung hapus (buat flash message).
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    /**
     * Flash data — cuma bertahan SATU request berikutnya (buat pesan sukses/error setelah redirect).
     */
    public static function flash(string $key, mixed $value): void
    {
        self::put("_flash.{$key}", $value);
        $old = self::get('_flash_keys', []);
        $old[] = $key;
        self::put('_flash_keys', array_unique($old));
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return self::get("_flash.{$key}", $default);
    }

    /**
     * WAJIB dipanggil di akhir request (di bootstrap/Application::run() setelah dispatch).
     * Bersihin flash data lama, dan persist ke DB kalau driver-nya database.
     */
    public static function commit(): void
    {
        $handler = self::driver();

        // Bersihin flash message yang udah "kepake" 1 request sebelumnya
        $flashKeys = $handler->get('_flash_keys', []);
        foreach ($flashKeys as $key) {
            $handler->forget("_flash.{$key}");
        }
        $handler->forget('_flash_keys');

        $handler->save();
    }
}
